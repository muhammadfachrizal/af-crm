<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
class EmailCampaignClick extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id'];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'email_campaign_clicks';
    }

    public function recipient()
    {
        return $this->belongsTo(EmailCampaignRecipient::class, 'email_campaign_recipient_id');
    }
}
