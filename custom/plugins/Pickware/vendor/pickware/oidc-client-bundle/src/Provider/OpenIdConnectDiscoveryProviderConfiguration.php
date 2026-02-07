<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\OidcClientBundle\Provider;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use UnexpectedValueException;

#[Exclude]
readonly class OpenIdConnectDiscoveryProviderConfiguration
{
    public function __construct(
        public string $wellKnownEndpoint,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public string $issuer,
        public string $userInfoEndpoint,
        /** @var string[] $scopes */
        public array $scopes,
    ) {
        // The Well-Known URL must be a secure https URL.
        // https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.3
        SecureUrlValidator::validate($wellKnownEndpoint);

        // The Authorization URL must be a secure https URL.
        // https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.3
        SecureUrlValidator::validate($authorizationEndpoint);

        // The Token URL must be a secure https URL.
        // https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.3
        SecureUrlValidator::validate($tokenEndpoint);

        // The JWKs URL must be a secure https URL.
        // https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.3
        SecureUrlValidator::validate($jwksUri);

        // The UserInfo URL must be a secure https URL.
        // https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.3
        SecureUrlValidator::validate($userInfoEndpoint);

        // Well-Known URL and JWT Issuer must have the same host and path.
        // https://openid.net/specs/openid-connect-discovery-1_0.html#rfc.section.4.3
        $deducedWellKnownEndpointFromIssuer = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        if ($deducedWellKnownEndpointFromIssuer !== $this->wellKnownEndpoint) {
            throw new UnexpectedValueException('The wellKnownUrl and jwtIssuer must have the same host and prefix');
        }
    }
}
