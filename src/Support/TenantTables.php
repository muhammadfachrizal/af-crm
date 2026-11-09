<?php

namespace VentureDrake\LaravelCrm\Support;

/** Daftar tabel yang memiliki kolom tenant_id. Tambahkan tabel baru di sini (dan pakai trait BelongsToTenant di modelnya). */
class TenantTables
{
    public static function all(): array
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        $names = [
            'activities',
            'addresses',
            'address_types',
            'attendances',
            'work_locations',
            'shifts',
            'calls',
            'chat_conversations',
            'chat_messages',
            'chat_visitors',
            'chat_visitor_page_views',
            'chat_widgets',
            'contacts',
            'contact_types',
            'customers',
            'deals',
            'deal_products',
            'deliveries',
            'delivery_products',
            'emails',
            'email_campaigns',
            'email_campaign_clicks',
            'email_campaign_recipients',
            'email_templates',
            'features',
            'feature_comments',
            'feature_statuses',
            'feature_views',
            'feature_votes',
            'fields',
            'field_groups',
            'field_models',
            'field_options',
            'field_values',
            'files',
            'industries',
            'invoices',
            'invoice_lines',
            'labels',
            'leads',
            'lead_sources',
            'lead_statuses',
            'lunches',
            'meetings',
            'monitors',
            'monitor_checks',
            'notes',
            'orders',
            'order_products',
            'organizations',
            'organization_types',
            'people',
            'phones',
            'pipelines',
            'pipeline_stages',
            'pipeline_stage_probabilities',
            'products',
            'product_attributes',
            'product_categories',
            'product_prices',
            'product_variations',
            'purchase_orders',
            'purchase_order_lines',
            'quotes',
            'quote_products',
            'settings',
            'sms_campaigns',
            'sms_campaign_clicks',
            'sms_campaign_recipients',
            'sms_templates',
            'tasks',
            'tax_rates',
            'usage_requests',
            'visits',
            'xero_contacts',
            'xero_invoices',
            'xero_items',
            'xero_people',
            'xero_purchase_orders'
        ];

        return array_merge(
            array_map(fn ($n) => $prefix.$n, $names),
            [config('permission.table_names.roles', 'roles'), 'users']
        );
    }
}
