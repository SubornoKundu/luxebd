<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Bulk product import from a CSV file (see the template download in the
 * admin panel). Products are matched by slug (made from the name): an existing
 * one is updated, otherwise a new one is created. Categories, sub-categories
 * and brands that don't exist yet are created on the fly.
 */
class ProductImporter
{
    public const COLUMNS = [
        'name', 'category', 'subcategory', 'brand', 'price', 'discount_price',
        'stock', 'description', 'image', 'featured', 'active',
    ];

    public const MAX_ROWS = 1000;

    public const PLACEHOLDER_IMAGE = '/images/placeholder.svg';

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $handle = fopen($path, 'r');

        if (! $handle) {
            $report['errors'][] = 'The file could not be read.';

            return $report;
        }

        $header = $this->readRow($handle);

        if (! $header) {
            fclose($handle);
            $report['errors'][] = 'The file is empty.';

            return $report;
        }

        $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(['name', 'category', 'brand', 'price', 'stock'], $header);

        if ($missing) {
            fclose($handle);
            $report['errors'][] = 'Missing column(s): '.implode(', ', $missing).'. Download the template to see the right layout.';

            return $report;
        }

        $line = 1;

        while (($cells = $this->readRow($handle)) !== false) {
            $line++;

            if ($line - 1 > self::MAX_ROWS) {
                $report['errors'][] = 'Stopped after '.self::MAX_ROWS.' rows.';
                break;
            }

            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }

            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'category' => ['required', 'string', 'max:255'],
                'subcategory' => ['nullable', 'string', 'max:255'],
                'brand' => ['required', 'string', 'max:255'],
                'price' => ['required', 'numeric', 'min:1', 'max:1000000'],
                'discount_price' => ['nullable', 'numeric', 'gt:0', 'lt:price'],
                'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
                'description' => ['nullable', 'string', 'max:5000'],
                'image' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $report['skipped']++;
                $report['errors'][] = "Row {$line}: ".$validator->errors()->first();

                continue;
            }

            try {
                DB::transaction(function () use ($row, &$report) {
                    $report[$this->save($row)]++;
                });
            } catch (\Throwable $e) {
                report($e);
                $report['skipped']++;
                $report['errors'][] = "Row {$line}: could not be saved.";
            }
        }

        fclose($handle);

        return $report;
    }

    /**
     * @param  array<string, string>  $row
     * @return 'created'|'updated'
     */
    protected function save(array $row): string
    {
        $category = $this->category($row['category'], null);

        if (($row['subcategory'] ?? '') !== '') {
            $category = $this->category($row['subcategory'], $category);
        }

        $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($row['brand'])])->first()
            ?? Brand::create(['name' => $row['brand'], 'slug' => $this->uniqueSlug(Brand::class, $row['brand'])]);

        $slug = Str::slug(mb_substr($row['name'], 0, 200)) ?: 'product';
        $product = Product::where('slug', $slug)->first()
            ?? Product::whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])->first();

        $attributes = [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'price' => (float) $row['price'],
            'discount_price' => ($row['discount_price'] ?? '') !== '' ? (float) $row['discount_price'] : null,
            'is_featured' => $this->truthy($row['featured'] ?? ''),
            'is_active' => ($row['active'] ?? '') === '' ? true : $this->truthy($row['active']),
        ];

        if ($product = ($product ?? null)) {
            // Updating: a blank / missing optional cell leaves the saved value alone.
            foreach ([
                'description' => 'description',
                'discount_price' => 'discount_price',
                'featured' => 'is_featured',
                'active' => 'is_active',
            ] as $column => $field) {
                if (($row[$column] ?? '') === '') {
                    unset($attributes[$field]);
                }
            }
        }

        $image = $this->imagePath($row['image'] ?? '');

        if ($product) {
            // A product with variants keeps its stock = sum of variants.
            if (! $product->variants()->exists()) {
                $attributes['stock'] = (int) $row['stock'];
            }

            if ($image) {
                $attributes['image'] = $image;
            }

            $product->update($attributes);

            return 'updated';
        }

        Product::create($attributes + [
            'slug' => $slug,
            'stock' => (int) $row['stock'],
            'image' => $image ?? self::PLACEHOLDER_IMAGE,
        ]);

        return 'created';
    }

    protected function category(string $name, ?Category $parent): Category
    {
        $existing = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($parent, fn ($q) => $q->where('parent_id', $parent->id), fn ($q) => $q->whereNull('parent_id'))
            ->first();

        return $existing ?? Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug(Category::class, $name),
            'parent_id' => $parent?->id,
        ]);
    }

    /**
     * Only web URLs or site-relative paths are accepted for the image column.
     */
    protected function imagePath(string $value): ?string
    {
        if ($value === '' || str_contains($value, '..')) {
            return null;
        }

        if (preg_match('#^https?://#i', $value) || str_starts_with($value, '/')) {
            return $value;
        }

        return null;
    }

    protected function truthy(string $value): bool
    {
        return in_array(mb_strtolower($value), ['1', 'yes', 'y', 'true', 'on'], true);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug(mb_substr($name, 0, 200)) ?: 'item';
        $slug = $base;
        $i = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @param  resource  $handle
     * @return list<string|null>|false
     */
    protected function readRow($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '');
    }
}
