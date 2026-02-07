<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DebugBundle\ResponseExceptionListener;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;

class JwtValidator
{
    public const PICKWARE_ISSUER = 'https://www.pickware.de';

    private Configuration $jwtConfiguration;

    public function __construct(string $relativePublicKeyPath, private ClockInterface $clock)
    {
        $publicKey = InMemory::file(__DIR__ . $relativePublicKeyPath);
        $signer = new Sha256();

        $this->jwtConfiguration = Configuration::forAsymmetricSigner(
            $signer,
            InMemory::plainText('no-private-key-necessary'),
            $publicKey,
        );
        $this->jwtConfiguration->setValidationConstraints(
            new IssuedBy(self::PICKWARE_ISSUER),
            new SignedWith($signer, $publicKey),
            new StrictValidAt($this->clock),
        );
    }

    public function isJwtTokenValid(string $token): bool
    {
        $parsedToken = $this->jwtConfiguration->parser()->parse($token);

        return $this->jwtConfiguration->validator()->validate(
            $parsedToken,
            ...$this->jwtConfiguration->validationConstraints(),
        );
    }
}
