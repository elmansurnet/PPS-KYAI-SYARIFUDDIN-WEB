<?php

namespace Modules\Pendaftaran\Policies;

class PendidikanTerakhirPolicy extends MasterDataPolicy
{
    protected function subject(): string
    {
        return 'PendidikanTerakhir';
    }
}
