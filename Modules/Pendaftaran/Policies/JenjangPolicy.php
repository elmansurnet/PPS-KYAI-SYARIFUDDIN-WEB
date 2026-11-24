<?php

namespace Modules\Pendaftaran\Policies;

class JenjangPolicy extends MasterDataPolicy
{
    protected function subject(): string
    {
        return 'Jenjang';
    }
}
