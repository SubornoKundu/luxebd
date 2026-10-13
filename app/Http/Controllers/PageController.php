<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * About / Contact / Returns / Privacy / Terms. The wording is generic but uses
 * the shop's own settings (name, contact details, shipping fee, free-shipping
 * limit), so it follows whatever the admin saves under Settings.
 */
class PageController extends Controller
{
    /** slug => page title */
    public const PAGES = [
        'about' => 'About us',
        'contact' => 'Contact us',
        'returns' => 'Returns & refunds',
        'privacy' => 'Privacy policy',
        'terms' => 'Terms & conditions',
    ];

    public function show(string $slug): Response
    {
        abort_unless(isset(self::PAGES[$slug]), 404);

        $shop = (string) config('site.name', config('app.name', 'Vireluxe'));
        $sym = (string) config('shop.currency_symbol', '৳');
        $fee = $sym.number_format((float) config('shop.shipping_fee'));
        $free = (float) config('shop.free_shipping_over');
        $freeText = $free > 0 ? ' Orders of '.$sym.number_format($free).' or more ship free.' : '';
        $email = (string) config('shop.contact.email');

        $pages = [
            'about' => [
                'intro' => "{$shop} is a perfume house for Bangladesh: signature fragrances, honest prices and delivery to your door.",
                'sections' => [
                    ['heading' => 'What we do', 'paragraphs' => [
                        "We sell perfumes for men, women and everyone in between. Every product page shows the real price, the scent notes, what is in stock and what other customers think.",
                    ]],
                    ['heading' => 'Why shop with us', 'items' => [
                        'Fragrances picked for how they last on the skin',
                        'Simple payment by bKash',
                        'Delivery in 1-2 days inside Dhaka and 3-5 days across Bangladesh',
                        '7-day replacement if an item is damaged or not as described',
                        'Friendly support on Facebook, Instagram and TikTok',
                    ]],
                ],
            ],
            'contact' => [
                'intro' => 'Questions about an order, a scent or a return? Reach us any of these ways and we will reply as soon as we can.',
                'sections' => [
                    ['heading' => 'Before you write', 'paragraphs' => [
                        'If your question is about an order, keep your order number (it looks like ORD-20261003-AB12CD) handy, or use the Track order page to see where it is right now.',
                    ]],
                ],
            ],
            'returns' => [
                'intro' => 'Not happy with what arrived? Here is how replacements and refunds work.',
                'sections' => [
                    ['heading' => '7-day replacement', 'paragraphs' => [
                        'If a bottle arrives damaged, leaking or different from what you ordered, tell us within 7 days of delivery. We will replace it at no extra cost. Because perfume is a personal item, we cannot take back a bottle that has been opened and used.',
                    ]],
                    ['heading' => 'How to ask', 'items' => [
                        'Message us on Facebook or Instagram with your order number and a photo of the problem',
                        'Keep the product and its packaging unused until we confirm the replacement',
                        'We arrange pickup and send the replacement as soon as the item is checked',
                    ]],
                    ['heading' => 'Refunds', 'paragraphs' => [
                        'If a replacement is not possible we refund you. Cancelled orders are refunded to the bKash number you paid from.',
                        'You can cancel an order yourself from My orders until it starts processing.',
                    ]],
                    ['heading' => 'Delivery charge', 'paragraphs' => [
                        "Delivery costs {$fee}.{$freeText} The delivery charge is not refunded for a change-of-mind return.",
                    ]],
                ],
            ],
            'privacy' => [
                'intro' => "How {$shop} looks after your information.",
                'sections' => [
                    ['heading' => 'What we collect', 'items' => [
                        'Your name, email and phone number when you register',
                        'Delivery addresses you save or enter at checkout',
                        'Your orders, wishlist and the reviews or questions you post',
                    ]],
                    ['heading' => 'How we use it', 'paragraphs' => [
                        'We use your details only to run your account, deliver and support your orders and send order updates by email. We do not sell your information.',
                    ]],
                    ['heading' => 'Payments', 'paragraphs' => [
                        'We never store your card number. We do not take card payments. Your bKash transaction id is used only to match your payment.',
                    ]],
                    ['heading' => 'Your choices', 'paragraphs' => [
                        'You can change your details and delete saved addresses from your account at any time. To close your account or remove your data, contact us'.($email ? " at {$email}" : '').'.',
                    ]],
                ],
            ],
            'terms' => [
                'intro' => "Please read these terms before ordering from {$shop}.",
                'sections' => [
                    ['heading' => 'Orders', 'paragraphs' => [
                        'An order is accepted when you receive the confirmation. If an item turns out to be out of stock we will tell you and cancel or adjust the order.',
                    ]],
                    ['heading' => 'Prices & payment', 'paragraphs' => [
                        "All prices are in Bangladeshi Taka ({$sym}). Prices and stock can change without notice, but the price you see at checkout is the price you pay. Payment is by bKash: send the total to our bKash number and enter the transaction id at checkout. We confirm the payment manually before we process the order.",
                    ]],
                    ['heading' => 'Delivery', 'paragraphs' => [
                        "We deliver in 1-2 working days inside Dhaka and 3-5 working days elsewhere in Bangladesh. Delivery costs {$fee}.{$freeText} Please give a correct address and phone number so the courier can reach you.",
                    ]],
                    ['heading' => 'Returns', 'paragraphs' => [
                        'Damaged or wrong bottles can be replaced within 7 days. See the Returns & refunds page for details.',
                    ]],
                    ['heading' => 'Your account', 'paragraphs' => [
                        'Keep your password private. We may suspend accounts that are used to cheat the shop or other customers.',
                    ]],
                ],
            ],
        ];

        $this->seo(self::PAGES[$slug].' - '.$shop, $pages[$slug]['intro']);

        return Inertia::render('Info/Show', [
            'slug' => $slug,
            'title' => self::PAGES[$slug],
            'intro' => $pages[$slug]['intro'],
            'sections' => $pages[$slug]['sections'],
        ]);
    }
}
