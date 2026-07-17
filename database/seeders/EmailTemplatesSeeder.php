<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateLayouts;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $summary = EmailTemplateLayouts::summaryBox();
        $customer = EmailTemplateLayouts::customerBox();

        $templates = [
            'order_confirmation_customer' => [
                'subject' => 'Hvala na narudžbi {{order_number}} — potvrda',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Potvrda narudžbe',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Uspješno smo primili vašu narudžbu <strong>{{order_number}}</strong>.
    U nastavku su detalji narudžbe.
</p>
{$summary}
<p style="margin:0 0 8px;font-size:15px;font-weight:bold;color:#111111;">Stavke narudžbe</p>
{{items_table}}
<p style="margin:16px 0 0;font-size:14px;line-height:1.6;color:#474747;">
    Status narudžbe možete pratiti putem linka ispod.
</p>
HTML,
                    withTrackingButton: true,
                ),
                'variables' => $this->orderVariables(),
            ],
            'order_notification_seller' => [
                'subject' => 'Nova narudžba {{order_number}} — {{customer_name}}',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Nova narudžba',
                    <<<HTML
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Stigla je nova narudžba <strong>{{order_number}}</strong>.
    Molimo obradite narudžbu u prodavačkom panelu.
</p>
{$customer}
{$summary}
<p style="margin:0 0 8px;font-size:15px;font-weight:bold;color:#111111;">Stavke narudžbe</p>
{{items_table}}
HTML,
                ),
                'variables' => $this->sellerVariables(),
            ],
            'order_cancelled_customer' => [
                'subject' => 'Narudžba {{order_number}} je otkazana',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Narudžba otkazana',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Obavještavamo vas da je narudžba <strong>{{order_number}}</strong> otkazana.
</p>
{$summary}
<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#474747;">
    Ako imate pitanja ili smatrate da je došlo do greške, kontaktirajte našu podršku.
    Novi status: <strong>{{new_status}}</strong>.
</p>
HTML,
                    withTrackingButton: true,
                ),
                'variables' => $this->statusVariables(),
            ],
            'order_cancelled_seller' => [
                'subject' => 'Otkazana narudžba {{order_number}}',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Narudžba otkazana',
                    <<<HTML
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Narudžba <strong>{{order_number}}</strong> je otkazana.
    Status promijenjen sa <strong>{{old_status}}</strong> na <strong>{{new_status}}</strong>.
</p>
{$customer}
{$summary}
HTML,
                ),
                'variables' => $this->statusVariables(),
            ],
            'order_shipped_customer' => [
                'subject' => 'Vaša narudžba {{order_number}} je poslana',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Narudžba poslana',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Vaša narudžba <strong>{{order_number}}</strong> je poslana i uskoro stiže na adresu:
    <strong>{{address}}, {{postal_code}} {{city}}</strong>.
</p>
{$summary}
<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#474747;">
    Status narudžbe možete pratiti u realnom vremenu putem linka ispod.
</p>
HTML,
                    withTrackingButton: true,
                ),
                'variables' => $this->statusVariables(),
            ],
            'order_shipped_seller' => [
                'subject' => 'Narudžba {{order_number}} označena kao poslana',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Narudžba poslana',
                    <<<HTML
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Narudžba <strong>{{order_number}}</strong> je označena kao poslana.
    Status: <strong>{{new_status}}</strong>.
</p>
{$customer}
{$summary}
HTML,
                ),
                'variables' => $this->statusVariables(),
            ],
            'order_completed_customer' => [
                'subject' => 'Narudžba {{order_number}} je uspješno isporučena',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Narudžba završena',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Vaša narudžba <strong>{{order_number}}</strong> je uspješno isporučena.
    Hvala vam što kupujete kod nas!
</p>
{$summary}
<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#474747;">
    Ako imate primjedbe ili trebate podršku, slobodno nas kontaktirajte.
</p>
HTML,
                    withTrackingButton: true,
                ),
                'variables' => $this->statusVariables(),
            ],
            'order_notification_admin' => [
                'subject' => 'Nova narudžba {{order_number}}',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Nova narudžba (admin)',
                    <<<HTML
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Nova narudžba: <strong>{{order_number}}</strong>
</p>
{$customer}
{$summary}
HTML,
                ),
                'variables' => $this->sellerVariables(),
            ],
            'loyalty_points_earned' => [
                'subject' => 'Osvojili ste {{points_earned}} BNC bodova',
                'body_html' => EmailTemplateLayouts::wrap(
                    'BNC bodovi',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Vaša narudžba <strong>{{order_number}}</strong> je isporučena. Dodijeljeno vam je
    <strong>{{points_earned}}</strong> BNC bodova.
</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Trenutno stanje: <strong>{{points_balance}}</strong> bodova.
</p>
<p style="margin:0;">
    <a href="{{account_url}}" style="display:inline-block;padding:12px 24px;background:#111111;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">
        Pogledajte bodove
    </a>
</p>
HTML,
                ),
                'variables' => $this->loyaltyVariables(),
            ],
            'loyalty_reward_unlocked' => [
                'subject' => 'Dostupna vam je nagrada: {{reward_name}}',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Nagrada dostupna',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Dostigli ste prag od <strong>{{points_required}}</strong> bodova i otključali nagradu
    <strong>{{reward_name}}</strong>.
</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    {{reward_description}}
</p>
<p style="margin:0;">
    <a href="{{account_url}}" style="display:inline-block;padding:12px 24px;background:#111111;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">
        Iskoristite nagradu
    </a>
</p>
HTML,
                ),
                'variables' => $this->loyaltyVariables(),
            ],
            'loyalty_guest_register_prompt' => [
                'subject' => 'Registrujte se i preuzmite {{points_earned}} BNC bodova',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Preuzmite bodove',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Vaša narudžba <strong>{{order_number}}</strong> je isporučena. Osvojili ste
    <strong>{{points_earned}}</strong> BNC bodova.
</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Registrujte se istim e-mailom da preuzmete bodove i koristite nagrade u sljedećim narudžbama.
</p>
<p style="margin:0;">
    <a href="{{register_url}}" style="display:inline-block;padding:12px 24px;background:#111111;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">
        Registracija
    </a>
</p>
HTML,
                ),
                'variables' => $this->loyaltyVariables(),
            ],
            'loyalty_card_issued' => [
                'subject' => 'Vaša BNC loyalty kartica {{card_number}}',
                'body_html' => EmailTemplateLayouts::wrap(
                    'Loyalty kartica',
                    <<<HTML
<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Poštovani {{first_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
    Vaša BNC loyalty kartica <strong>{{card_number}}</strong> je aktivna.
    Pokažite je u radnji pri kupovini da biste skupljali i koristili bodove.
</p>
<p style="margin:0;">
    <a href="{{account_url}}" style="display:inline-block;padding:12px 24px;background:#111111;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">
        Pogledajte bodove
    </a>
</p>
HTML,
                ),
                'variables' => $this->loyaltyVariables(),
            ],
        ];

        foreach ($templates as $slug => $template) {
            EmailTemplate::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'subject' => $template['subject'],
                    'body_html' => $template['body_html'],
                    'variables' => $template['variables'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function orderVariables(): array
    {
        return [
            'order_number',
            'first_name',
            'last_name',
            'customer_name',
            'email',
            'phone',
            'address',
            'city',
            'postal_code',
            'order_date',
            'payment_method',
            'shipping_method',
            'subtotal',
            'discount_total',
            'shipping_fee',
            'total',
            'currency',
            'items_count',
            'items_table',
            'tracking_url',
            'store_name',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sellerVariables(): array
    {
        return array_merge($this->orderVariables(), [
            'company_name',
            'notes',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function loyaltyVariables(): array
    {
        return [
            'first_name',
            'points_earned',
            'points_balance',
            'points_required',
            'reward_name',
            'reward_description',
            'order_number',
            'register_url',
            'account_url',
            'store_name',
            'card_number',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function statusVariables(): array
    {
        return array_merge($this->orderVariables(), [
            'old_status',
            'new_status',
        ]);
    }
}
