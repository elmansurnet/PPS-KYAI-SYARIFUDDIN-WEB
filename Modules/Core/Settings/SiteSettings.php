<?php

namespace Modules\Core\Settings;

use Spatie\LaravelSettings\Settings;

class SiteSettings extends Settings
{
    public string $site_name;

    public ?string $logo_path;

    public ?string $address;

    public ?string $contact_email;

    /** Daftar item dengan kunci platform dan url (docblock sengaja polos: parser tipe Spatie tidak mengenal array shape). */
    public array $social_links;

    public string $whatsapp_number;

    public string $whatsapp_message_template;

    public ?string $fee_pdf_path;

    public static function group(): string
    {
        return 'site';
    }
}
