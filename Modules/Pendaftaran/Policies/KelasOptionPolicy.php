<?php

namespace Modules\Pendaftaran\Policies;

class KelasOptionPolicy extends MasterDataPolicy
{
    protected function subject(): string
    {
        return 'KelasOption';
    }
}
