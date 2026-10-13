<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared list + modal-form CRUD for the small catalogue tables (categories,
 * brands, banners, coupons). Subclasses only describe the model, the columns
 * and form fields shown, and the validation rules; the generic Vue page
 * Admin/Resource/Index renders it.
 */
abstract class ResourceController extends Controller
{
    use HandlesImageUploads;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function title(): string;

    /** Admin URL prefix, e.g. "/admin/categories". */
    abstract protected function base(): string;

    /** @return list<array<string, mixed>> */
    abstract protected function columns(): array;

    /** @return list<array<string, mixed>> */
    abstract protected function fields(): array;

    /** @return array<string, mixed> */
    abstract protected function rules(?Model $item): array;

    /** Column used by the search box, or null for no search. */
    protected function searchColumn(): ?string
    {
        return 'name';
    }

    /** Sub-folder for uploaded images. */
    protected function imageDir(): string
    {
        return Str::kebab(class_basename($this->model()));
    }

    /** Return a message to block deletion, or null to allow it. */
    protected function deleteBlocker(Model $item): ?string
    {
        return null;
    }

    /**
     * Last chance to adjust validated data before saving.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $item): array
    {
        return $data;
    }

    /**
     * Hook to add display-only attributes to the page of items.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Model>  $items
     */
    protected function decorate($items): void {}

    protected function query(Request $request): Builder
    {
        $model = $this->model();

        return $model::query()
            ->when($this->searchColumn() && $request->filled('q'), fn ($q) => $q->where(
                $this->searchColumn(), 'like', '%'.addcslashes((string) $request->input('q'), '%_\\').'%',
            ))
            ->latest('id');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Resource/Index', [
            'title' => $this->title(),
            'base' => $this->base(),
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'items' => tap($this->query($request)->paginate(12)->withQueryString(), fn ($items) => $this->decorate($items)),
            'filters' => ['q' => $request->input('q', '')],
            'searchable' => $this->searchColumn() !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(null));
        $data = $this->prepare($this->withImages($request, $data, null), null);

        $model = $this->model();
        $model::create($data);

        return back()->with('success', Str::singular($this->title()).' created.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $model = $this->model();
        $item = $model::findOrFail($id);

        $data = $request->validate($this->rules($item));
        $data = $this->prepare($this->withImages($request, $data, $item), $item);

        $item->update($data);

        return back()->with('success', Str::singular($this->title()).' updated.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $model = $this->model();
        $item = $model::findOrFail($id);

        if ($message = $this->deleteBlocker($item)) {
            return back()->with('error', $message);
        }

        foreach ($this->imageKeys() as $key) {
            $this->deleteImage($item->{$key});
        }

        $item->delete();

        return back()->with('success', Str::singular($this->title()).' deleted.');
    }

    /** @return list<string> */
    protected function imageKeys(): array
    {
        return collect($this->fields())->where('type', 'image')->pluck('key')->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withImages(Request $request, array $data, ?Model $item): array
    {
        foreach ($this->imageKeys() as $key) {
            if ($request->hasFile($key)) {
                if ($item) {
                    $this->deleteImage($item->{$key});
                }

                $data[$key] = $this->storeImage($request->file($key), $this->imageDir());
            } else {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function uniqueSlug(string $name, ?Model $ignore = null): string
    {
        $model = $this->model();
        $base = Str::slug(mb_substr($name, 0, 200)) ?: 'item';
        $slug = $base;
        $i = 2;

        while ($model::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
