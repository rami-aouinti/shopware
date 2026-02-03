<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api;

enum DsvPackageType: string
{
    case Bag = 'BAG';
    case Case = 'CAS';
    case Colli = 'CLL';
    case Create = 'CRT';
    case Carton = 'CTN';
    case Drum = 'DRM';
    case Can = 'CAN';
    case Pallet120x80 = 'EUP';
    case EuroPallet = 'EUR';
    case Gitterbox = 'GIB';
    case HalfPallet = 'HPL';
    case Ibc = 'IBC';
    case Ipl = 'IPL';
    case JerryCan = 'JCN';
    case QuarterPallet = 'QPL';
    case Load = 'LOD';
    case PalletUnspecified = 'PLT';
    case PalletContainer = 'CON';
    case Pallet120x120 = 'PXL';
    case Pallet120x110 = 'UPL';
}
