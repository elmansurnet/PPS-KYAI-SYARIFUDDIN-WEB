<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.site_name', 'PPs Kyai Syarifuddin');
        $this->migrator->add('site.logo_path', null);
        $this->migrator->add('site.address', null);
        $this->migrator->add('site.contact_email', null);
        $this->migrator->add('site.social_links', []);
        $this->migrator->add('site.whatsapp_number', '');
        $this->migrator->add('site.whatsapp_message_template', "Assalamu'alaikum, saya sudah mendaftarkan {nama} (NIK ...{nik_akhir}) melalui website. Mohon ditindaklanjuti.");
        $this->migrator->add('site.fee_pdf_path', null);
    }
};
