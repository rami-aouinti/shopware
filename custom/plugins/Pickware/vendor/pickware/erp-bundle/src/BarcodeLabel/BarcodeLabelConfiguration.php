<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel;

use InvalidArgumentException;

class BarcodeLabelConfiguration
{
    private string $barcodeLabelType = '';
    private string $layout = BarcodeLabelLayouts::LAYOUT_A;
    private int $widthInMillimeter = 60;
    private int $heightInMillimeter = 40;
    private int $marginTopInMillimeter = 3;
    private int $marginLeftInMillimeter = 3;
    private int $marginRightInMillimeter = 3;
    private int $marginBottomInMillimeter = 3;
    private array $dataProviderParams = [];

    public static function fromArray(array $array): self
    {
        $self = new self();

        foreach (array_keys(get_object_vars($self)) as $key) {
            if (!isset($array[$key])) {
                throw new InvalidArgumentException(sprintf('Property "%s" is missing.', $key));
            }
        }

        $self->setBarcodeLabelType($array['barcodeLabelType']);
        $self->setLayout($array['layout']);
        $self->setWidthInMillimeter($array['widthInMillimeter']);
        $self->setHeightInMillimeter($array['heightInMillimeter']);
        $self->setMarginTopInMillimeter($array['marginTopInMillimeter']);
        $self->setMarginLeftInMillimeter($array['marginLeftInMillimeter']);
        $self->setMarginRightInMillimeter($array['marginRightInMillimeter']);
        $self->setMarginBottomInMillimeter($array['marginBottomInMillimeter']);
        $self->setDataProviderParams($array['dataProviderParams']);

        return $self;
    }

    public function getBarcodeLabelType(): string
    {
        return $this->barcodeLabelType;
    }

    public function setBarcodeLabelType(string $barcodeLabelType): void
    {
        $this->barcodeLabelType = $barcodeLabelType;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function getWidthInMillimeter(): int
    {
        return $this->widthInMillimeter;
    }

    public function setWidthInMillimeter(int $widthInMillimeter): void
    {
        $this->widthInMillimeter = $widthInMillimeter;
    }

    public function getHeightInMillimeter(): int
    {
        return $this->heightInMillimeter;
    }

    public function setHeightInMillimeter(int $heightInMillimeter): void
    {
        $this->heightInMillimeter = $heightInMillimeter;
    }

    public function getMarginTopInMillimeter(): int
    {
        return $this->marginTopInMillimeter;
    }

    public function setMarginTopInMillimeter(int $marginTopInMillimeter): void
    {
        $this->marginTopInMillimeter = $marginTopInMillimeter;
    }

    public function getMarginLeftInMillimeter(): int
    {
        return $this->marginLeftInMillimeter;
    }

    public function setMarginLeftInMillimeter(int $marginLeftInMillimeter): void
    {
        $this->marginLeftInMillimeter = $marginLeftInMillimeter;
    }

    public function getMarginRightInMillimeter(): int
    {
        return $this->marginRightInMillimeter;
    }

    public function setMarginRightInMillimeter(int $marginRightInMillimeter): void
    {
        $this->marginRightInMillimeter = $marginRightInMillimeter;
    }

    public function getMarginBottomInMillimeter(): int
    {
        return $this->marginBottomInMillimeter;
    }

    public function setMarginBottomInMillimeter(int $marginBottomInMillimeter): void
    {
        $this->marginBottomInMillimeter = $marginBottomInMillimeter;
    }

    public function getDataProviderParams(): array
    {
        return $this->dataProviderParams;
    }

    public function getDataProviderParamValueByKey(string $key, $default = null)
    {
        return isset($this->dataProviderParams[$key]) ? $this->dataProviderParams[$key] : $default;
    }

    public function setDataProviderParams(array $dataProviderParams): void
    {
        $this->dataProviderParams = $dataProviderParams;
    }
}
