<?php

namespace Modules\Pendaftaran\Policies;

use App\Models\User;
use Modules\Pendaftaran\Models\Applicant;

/**
 * Izin data pendaftar (gaya Shield "Aksi:Applicant", ditulis tangan; jangan generate ulang tanpa
 * --option=permissions). Superadmin melewati lewat Gate::before.
 *
 * - Update:Applicant hanya untuk bendera status dan catatan panitia (tidak ada form edit data pendaftar).
 * - Tidak ada create: pendaftar hanya masuk dari formulir publik.
 * - Delete/DeleteAny: admin saja. Hapus massal tetap memeriksa delete per rekaman (authorizeIndividualRecords).
 * - Izin khusus: Export (Excel/PDF), RevealNik (NIK lengkap), DownloadKk (unduh file KK). Semuanya dicatat di AuditLog.
 */
class ApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Applicant');
    }

    public function view(User $user, Applicant $applicant): bool
    {
        return $user->can('View:Applicant');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Applicant $applicant): bool
    {
        return $user->can('Update:Applicant');
    }

    public function delete(User $user, Applicant $applicant): bool
    {
        return $user->can('Delete:Applicant');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Applicant');
    }

    public function export(User $user): bool
    {
        return $user->can('ViewAny:Applicant') && $user->can('Export:Applicant');
    }

    public function revealNik(User $user, ?Applicant $applicant = null): bool
    {
        return $user->can('View:Applicant') && $user->can('RevealNik:Applicant');
    }

    public function downloadKk(User $user, Applicant $applicant): bool
    {
        return $user->can('View:Applicant') && $user->can('DownloadKk:Applicant');
    }
}
