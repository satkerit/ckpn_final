<?php

declare(strict_types=1);

namespace App\Enums;

enum RunType: string
{
    case Classification = 'classification';
    case PdNetflow = 'pd_netflow';
    case PdMigration = 'pd_migration';
    case LgdEr = 'lgd_er';
    case LgdCs = 'lgd_cs';
    case LgdFinal = 'lgd_final';
    case CkpnIndividual = 'ckpn_individual';
    case CkpnCollective = 'ckpn_collective';
}
