<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\MailTemplate;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class MailTemplateTranslation
{
    private string $typeName;
    private string $localeCode;
    private string $subject;
    private string $sender;
    private string $description;
    private string $contentHtml;
    private string $contentPlain;

    /**
     * @return self[]
     */
    public static function loadFromDir(string $dir): array
    {
        $files = scandir($dir);
        $yamlFiles = array_values(array_filter($files, fn(string $file) => str_ends_with($file, '.yaml') || str_ends_with($file, '.yml')));

        return array_map(fn(string $yamlFile) => self::createFromYamlFile($dir . '/' . $yamlFile), $yamlFiles);
    }

    public static function createFromYamlFile(string $filename): self
    {
        try {
            $mailTemplateTranslation = Yaml::parseFile($filename);
        } catch (ParseException $e) {
            // throw runtime exception because an invalid yaml is ALWAYS a programming error here.
            throw new RuntimeException(sprintf(
                'Yaml file %s could not be parsed: %s',
                $filename,
                $e->getMessage(),
            ), 0, $e);
        }
        $mailTemplateTranslation['contentHtml'] = file_get_contents(
            dirname($filename) . '/' . $mailTemplateTranslation['contentHtml']['fromFile'],
        );
        $mailTemplateTranslation['contentPlain'] = file_get_contents(
            dirname($filename) . '/' . $mailTemplateTranslation['contentPlain']['fromFile'],
        );

        return self::fromArray($mailTemplateTranslation);
    }

    public static function fromArray(array $array): self
    {
        $self = new self();
        $self->localeCode = $array['localeCode'];
        $self->typeName = $array['typeName'];
        $self->subject = $array['subject'];
        $self->sender = $array['sender'];
        $self->description = $array['description'];
        $self->contentHtml = $array['contentHtml'];
        $self->contentPlain = $array['contentPlain'];

        return $self;
    }

    private function __construct() {}

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function getContentPlain(): string
    {
        return $this->contentPlain;
    }
}
