<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Authentication;

use Pickware\PhpStandardLibrary\Json\Json;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Session
{
    private function __construct(
        private readonly Token $token,
        private readonly string $username,
        private readonly string $passwordHash,
    ) {}

    public static function create(Token $token, Credentials $credentials): self
    {
        return new self(
            token: $token,
            username: $credentials->getUsername(),
            passwordHash: password_hash($credentials->getPassword(), PASSWORD_DEFAULT),
        );
    }

    public function getToken(): Token
    {
        return $this->token;
    }

    public function matches(Credentials $credentials): bool
    {
        return $this->username === $credentials->getUsername()
            && password_verify($credentials->getPassword(), $this->passwordHash);
    }

    public function toString(): string
    {
        return Json::stringify([
            'username' => $this->username,
            'passwordHash' => $this->passwordHash,
            'token' => $this->token,
        ]);
    }

    public static function fromString(string $string): self
    {
        $array = Json::decodeToArray($string);

        return new self(
            Token::fromArray($array['token']),
            $array['username'],
            $array['passwordHash'],
        );
    }
}
