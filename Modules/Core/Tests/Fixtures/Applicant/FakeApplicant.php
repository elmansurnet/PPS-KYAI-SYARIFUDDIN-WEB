<?php

namespace Modules\Core\Tests\Fixtures\Applicant;

use Illuminate\Database\Eloquent\Model;

/** Model tiruan khusus tes; pengganti Pendaftar yang baru ada di Task 12. */
class FakeApplicant extends Model
{
    protected $table = 'fake_applicants';

    protected $guarded = [];
}
