<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use InvalidArgumentException;
use JsonSerializable;

class Country implements JsonSerializable
{
    private const COUNTRY_CODE_MAPPINGS = [
        'af' => 'afg',
        'ax' => 'ala',
        'al' => 'alb',
        'dz' => 'dza',
        'as' => 'asm',
        'ad' => 'and',
        'ao' => 'ago',
        'ai' => 'aia',
        'aq' => 'ata',
        'ag' => 'atg',
        'ar' => 'arg',
        'am' => 'arm',
        'aw' => 'abw',
        'au' => 'aus',
        'at' => 'aut',
        'az' => 'aze',
        'bs' => 'bhs',
        'bh' => 'bhr',
        'bd' => 'bgd',
        'bb' => 'brb',
        'by' => 'blr',
        'be' => 'bel',
        'bz' => 'blz',
        'bj' => 'ben',
        'bm' => 'bmu',
        'bt' => 'btn',
        'bo' => 'bol',
        'bq' => 'bes',
        'ba' => 'bih',
        'bw' => 'bwa',
        'bv' => 'bvt',
        'br' => 'bra',
        'io' => 'iot',
        'bn' => 'brn',
        'bg' => 'bgr',
        'bf' => 'bfa',
        'bi' => 'bdi',
        'kh' => 'khm',
        'cm' => 'cmr',
        'ca' => 'can',
        'cv' => 'cpv',
        'ky' => 'cym',
        'cf' => 'caf',
        'td' => 'tcd',
        'cl' => 'chl',
        'cn' => 'chn',
        'cx' => 'cxr',
        'cc' => 'cck',
        'co' => 'col',
        'km' => 'com',
        'cg' => 'cog',
        'cd' => 'cod',
        'ck' => 'cok',
        'cr' => 'cri',
        'ci' => 'civ',
        'hr' => 'hrv',
        'cu' => 'cub',
        'cw' => 'cuw',
        'cy' => 'cyp',
        'cz' => 'cze',
        'dk' => 'dnk',
        'dj' => 'dji',
        'dm' => 'dma',
        'do' => 'dom',
        'ec' => 'ecu',
        'eg' => 'egy',
        'sv' => 'slv',
        'gq' => 'gnq',
        'er' => 'eri',
        'ee' => 'est',
        'sz' => 'swz',
        'et' => 'eth',
        'fk' => 'flk',
        'fo' => 'fro',
        'fj' => 'fji',
        'fi' => 'fin',
        'fr' => 'fra',
        'gf' => 'guf',
        'pf' => 'pyf',
        'tf' => 'atf',
        'ga' => 'gab',
        'gm' => 'gmb',
        'ge' => 'geo',
        'de' => 'deu',
        'gh' => 'gha',
        'gi' => 'gib',
        'gr' => 'grc',
        'gl' => 'grl',
        'gd' => 'grd',
        'gp' => 'glp',
        'gu' => 'gum',
        'gt' => 'gtm',
        'gg' => 'ggy',
        'gn' => 'gin',
        'gw' => 'gnb',
        'gy' => 'guy',
        'ht' => 'hti',
        'hm' => 'hmd',
        'va' => 'vat',
        'hn' => 'hnd',
        'hk' => 'hkg',
        'hu' => 'hun',
        'is' => 'isl',
        'in' => 'ind',
        'id' => 'idn',
        'ir' => 'irn',
        'iq' => 'irq',
        'ie' => 'irl',
        'im' => 'imn',
        'il' => 'isr',
        'it' => 'ita',
        'jm' => 'jam',
        'jp' => 'jpn',
        'je' => 'jey',
        'jo' => 'jor',
        'kz' => 'kaz',
        'ke' => 'ken',
        'ki' => 'kir',
        'kp' => 'prk',
        'kr' => 'kor',
        'kw' => 'kwt',
        'kg' => 'kgz',
        'la' => 'lao',
        'lv' => 'lva',
        'lb' => 'lbn',
        'ls' => 'lso',
        'lr' => 'lbr',
        'ly' => 'lby',
        'li' => 'lie',
        'lt' => 'ltu',
        'lu' => 'lux',
        'mo' => 'mac',
        'mg' => 'mdg',
        'mw' => 'mwi',
        'my' => 'mys',
        'mv' => 'mdv',
        'ml' => 'mli',
        'mt' => 'mlt',
        'mh' => 'mhl',
        'mq' => 'mtq',
        'mr' => 'mrt',
        'mu' => 'mus',
        'yt' => 'myt',
        'mx' => 'mex',
        'fm' => 'fsm',
        'md' => 'mda',
        'mc' => 'mco',
        'mn' => 'mng',
        'me' => 'mne',
        'ms' => 'msr',
        'ma' => 'mar',
        'mz' => 'moz',
        'mm' => 'mmr',
        'na' => 'nam',
        'nr' => 'nru',
        'np' => 'npl',
        'nl' => 'nld',
        'nc' => 'ncl',
        'nz' => 'nzl',
        'ni' => 'nic',
        'ne' => 'ner',
        'ng' => 'nga',
        'nu' => 'niu',
        'nf' => 'nfk',
        'mk' => 'mkd',
        'mp' => 'mnp',
        'no' => 'nor',
        'om' => 'omn',
        'pk' => 'pak',
        'pw' => 'plw',
        'ps' => 'pse',
        'pa' => 'pan',
        'pg' => 'png',
        'py' => 'pry',
        'pe' => 'per',
        'ph' => 'phl',
        'pn' => 'pcn',
        'pl' => 'pol',
        'pt' => 'prt',
        'pr' => 'pri',
        'qa' => 'qat',
        're' => 'reu',
        'ro' => 'rou',
        'ru' => 'rus',
        'rw' => 'rwa',
        'bl' => 'blm',
        'sh' => 'shn',
        'kn' => 'kna',
        'lc' => 'lca',
        'mf' => 'maf',
        'pm' => 'spm',
        'vc' => 'vct',
        'ws' => 'wsm',
        'sm' => 'smr',
        'st' => 'stp',
        'sa' => 'sau',
        'sn' => 'sen',
        'rs' => 'srb',
        'sc' => 'syc',
        'sl' => 'sle',
        'sg' => 'sgp',
        'sx' => 'sxm',
        'sk' => 'svk',
        'si' => 'svn',
        'sb' => 'slb',
        'so' => 'som',
        'za' => 'zaf',
        'gs' => 'sgs',
        'ss' => 'ssd',
        'es' => 'esp',
        'lk' => 'lka',
        'sd' => 'sdn',
        'sr' => 'sur',
        'sj' => 'sjm',
        'se' => 'swe',
        'ch' => 'che',
        'sy' => 'syr',
        'tw' => 'twn',
        'tj' => 'tjk',
        'tz' => 'tza',
        'th' => 'tha',
        'tl' => 'tls',
        'tg' => 'tgo',
        'tk' => 'tkl',
        'to' => 'ton',
        'tt' => 'tto',
        'tn' => 'tun',
        'tr' => 'tur',
        'tm' => 'tkm',
        'tc' => 'tca',
        'tv' => 'tuv',
        'ug' => 'uga',
        'ua' => 'ukr',
        'ae' => 'are',
        'gb' => 'gbr',
        'us' => 'usa',
        'um' => 'umi',
        'uy' => 'ury',
        'uz' => 'uzb',
        'vu' => 'vut',
        've' => 'ven',
        'vn' => 'vnm',
        'vg' => 'vgb',
        'vi' => 'vir',
        'wf' => 'wlf',
        'eh' => 'esh',
        'ye' => 'yem',
        'zm' => 'zmb',
        'zw' => 'zwe',
    ];

    private string $iso2Code;

    public function __construct(string $isoCode)
    {
        $isoCode = mb_strtolower($isoCode);

        if (mb_strlen($isoCode) === 3) {
            $isoCode = array_flip(self::COUNTRY_CODE_MAPPINGS)[$isoCode] ?? $isoCode;
        }
        if (mb_strlen($isoCode) !== 2) {
            throw new InvalidArgumentException(sprintf(
                'The given country code "%s" is invalid. Please provide a valid ISO 3166-1 alpha-2 country code.',
                $isoCode,
            ));
        }

        $this->iso2Code = $isoCode;
    }

    public function jsonSerialize(): array
    {
        return [
            'iso2Code' => $this->iso2Code,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self($array['iso2Code']);
    }

    public function getIso3Code(): string
    {
        return self::COUNTRY_CODE_MAPPINGS[$this->iso2Code];
    }

    public function getIso2Code(): string
    {
        return $this->iso2Code;
    }

    public function equals(Country $other): bool
    {
        return $this->iso2Code === $other->iso2Code;
    }
}
