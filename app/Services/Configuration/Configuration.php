<?php

/**
 * Configuration.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III YNAB importer
 * (https://github.com/firefly-iii/ynab-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Configuration.php

 */

declare(strict_types=1);

namespace App\Services\Configuration;

use Carbon\Carbon;
use RuntimeException;

/**
 * Class Configuration.
 */
class Configuration
{
    /** @var int */
    public const VERSION = 1;
    /** @var array */
    private $accountTypes;
    /** @var array */
    private $accounts;
    /** @var array */
    private $budgets;
    /** @var string */
    private $dateNotAfter;
    /** @var string */
    private $dateNotBefore;
    /** @var string */
    private $dateRange;
    /** @var int */
    private $dateRangeNumber;
    /** @var string */
    private $dateRangeUnit;
    /** @var bool */
    private $doMapping;
    /** @var array */
    private $mapping;
    /** @var bool */
    private $rules;
    /** @var bool */
    private $skipBudgetSelection;
    /** @var bool */
    private $skipForm;
    /** @var int */
    private $version;

    /**
     * Configuration constructor.
     */
    private function __construct()
    {
        $this->version               = self::VERSION;
        $this->budgets               = [];
        $this->skipBudgetSelection   = false;
        $this->skipConfigurationForm = false;
        $this->dateNotBefore         = '';
        $this->dateNotAfter          = '';
        $this->dateRange             = 'all';
        $this->dateRangeNumber       = 30;
        $this->dateRangeUnit         = 'd';
        $this->doMapping             = false;
        $this->mapping               = [];
        $this->rules                 = true;
        $this->skipForm              = false;
        $this->accounts              = [];
        $this->accountTypes          = [];
    }

    /**
     * @param array $array
     *
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $version                     = $array['version'] ?? 1;
        $object                      = new self;
        $object->version             = $version;
        $object->budgets             = $array['budgets'] ?? [];
        $object->skipBudgetSelection = $array['skip_budget_selection'] ?? false;
        $object->dateNotBefore       = $array['date_not_before'] ?? '';
        $object->dateNotAfter        = $array['date_not_after'] ?? '';
        $object->dateRange           = $array['date_range'] ?? 'all';
        $object->dateRangeNumber     = $array['date_range_number'] ?? 30;
        $object->dateRangeUnit       = $array['date_range_unit'] ?? 'd';
        $object->doMapping           = $array['do_mapping'] ?? false;
        $object->mapping             = $array['mapping'] ?? [];
        $object->rules               = $array['rules'] ?? true;
        $object->skipForm            = $array['skip_form'] ?? false;
        $object->accounts            = $array['accounts'] ?? [];

        return $object;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public static function fromFile(array $data): self
    {
        app('log')->debug('Now in Configuration::fromFile', $data);
        $version = $data['version'] ?? 1;
        if (1 === $version) {
            return self::fromDefaultFile($data);
        }
        throw new RuntimeException(sprintf('Configuration file version "%s" cannot be parsed.', $version));
    }

    /**
     * @param string $unit
     * @param int    $number
     *
     * @return string|null
     */
    private static function calcDateNotBefore(string $unit, int $number): ?string
    {
        $functions = [
            'd' => 'subDays',
            'w' => 'subWeeks',
            'm' => 'subMonths',
            'y' => 'subYears',
        ];
        if (isset($functions[$unit])) {
            $today    = Carbon::now();
            $function = $functions[$unit];
            $today->$function($number);

            return $today->format('Y-m-d');
        }
        app('log')->error(sprintf('Could not parse date setting. Unknown key "%s"', $unit));

        return null;
    }

    /**
     * @param array $array
     *
     * @return static
     */
    private static function fromDefaultFile(array $array): self
    {
        $object                      = new self;
        $object->version             = $array['version'] ?? self::VERSION;
        $object->budgets             = $array['budgets'] ?? [];
        $object->skipBudgetSelection = $array['skip_budget_selection'] ?? false;
        $object->dateNotBefore       = $array['date_not_before'] ?? '';
        $object->dateNotAfter        = $array['date_not_after'] ?? '';
        $object->dateRange           = $array['date_range'] ?? 'all';
        $object->dateRangeNumber     = $array['date_range_number'] ?? 30;
        $object->dateRangeUnit       = $array['date_range_unit'] ?? 'd';
        $object->doMapping           = $array['do_mapping'] ?? false;
        $object->mapping             = $array['mapping'] ?? [];
        $object->rules               = $array['rules'] ?? true;
        $object->skipForm            = $array['skip_form'] ?? false;
        $object->accounts            = $array['accounts'] ?? [];

        if ('partial' === $object->dateRange) {
            $object->dateNotBefore = self::calcDateNotBefore($object->dateRangeUnit, $object->dateRangeNumber);
        }

        return $object;
    }

    /**
     * @return array
     */
    public function getAccountTypes(): array
    {
        return $this->accountTypes;
    }

    /**
     * @param array $accountTypes
     */
    public function setAccountTypes(array $accountTypes): void
    {
        $this->accountTypes = $accountTypes;
    }

    /**
     * @return array
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    /**
     * @param array $accounts
     */
    public function setAccounts(array $accounts): void
    {
        $this->accounts = $accounts;
    }

    /**
     * @return array
     */
    public function getBudgets(): array
    {
        return $this->budgets;
    }

    /**
     * @param array $budgets
     */
    public function setBudgets(array $budgets): void
    {
        $this->budgets = $budgets;
    }

    /**
     * @return string
     */
    public function getDateNotAfter(): string
    {
        return $this->dateNotAfter;
    }

    /**
     * @param string $dateNotAfter
     */
    public function setDateNotAfter(string $dateNotAfter): void
    {
        $this->dateNotAfter = $dateNotAfter;
    }

    /**
     * @return string
     */
    public function getDateNotBefore(): string
    {
        return $this->dateNotBefore;
    }

    /**
     * @param string $dateNotBefore
     */
    public function setDateNotBefore(string $dateNotBefore): void
    {
        $this->dateNotBefore = $dateNotBefore;
    }

    /**
     * @return string
     */
    public function getDateRange(): string
    {
        return $this->dateRange;
    }

    /**
     * @param string $dateRange
     */
    public function setDateRange(string $dateRange): void
    {
        $this->dateRange = $dateRange;
    }

    /**
     * @return int
     */
    public function getDateRangeNumber(): int
    {
        return $this->dateRangeNumber;
    }

    /**
     * @param int $dateRangeNumber
     */
    public function setDateRangeNumber(int $dateRangeNumber): void
    {
        $this->dateRangeNumber = $dateRangeNumber;
    }

    /**
     * @return string
     */
    public function getDateRangeUnit(): string
    {
        return $this->dateRangeUnit;
    }

    /**
     * @param string $dateRangeUnit
     */
    public function setDateRangeUnit(string $dateRangeUnit): void
    {
        $this->dateRangeUnit = $dateRangeUnit;
    }

    /**
     * @return array
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * @param array $mapping
     */
    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function isDoMapping(): bool
    {
        return $this->doMapping;
    }

    /**
     * @param bool $doMapping
     */
    public function setDoMapping(bool $doMapping): void
    {
        $this->doMapping = $doMapping;
    }

    /**
     * @return bool
     */
    public function isRules(): bool
    {
        return $this->rules;
    }

    /**
     * @param bool $rules
     */
    public function setRules(bool $rules): void
    {
        $this->rules = $rules;
    }

    /**
     * @return bool
     */
    public function isSkipBudgetSelection(): bool
    {
        return $this->skipBudgetSelection;
    }

    /**
     * @param bool $skipBudgetSelection
     */
    public function setSkipBudgetSelection(bool $skipBudgetSelection): void
    {
        $this->skipBudgetSelection = $skipBudgetSelection;
    }

    /**
     * @return bool
     */
    public function isSkipForm(): bool
    {
        return $this->skipForm;
    }

    /**
     * @param bool $skipForm
     */
    public function setSkipForm(bool $skipForm): void
    {
        $this->skipForm = $skipForm;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'version'               => $this->version,
            'budgets'               => $this->budgets,
            'skip_budget_selection' => $this->skipBudgetSelection,
            'date_not_before'       => $this->dateNotBefore,
            'date_not_after'        => $this->dateNotAfter,
            'date_range'            => $this->dateRange,
            'date_range_number'     => $this->dateRangeNumber,
            'date_range_unit'       => $this->dateRangeUnit,
            'do_mapping'            => $this->doMapping,
            'mapping'               => $this->mapping,
            'rules'                 => $this->rules,
            'skip_form'             => $this->skipForm,
            'accounts'              => $this->accounts,
        ];
    }

    /**
     *
     */
    public function updateDates(): void
    {
        // respond to date range in request:
        switch ($this->dateRange) {
            case 'all':
                $this->dateRangeUnit   = null;
                $this->dateRangeNumber = null;
                $this->dateNotBefore   = null;
                $this->dateNotAfter    = null;
                break;
            case 'partial':
                $this->dateNotAfter  = null;
                $this->dateNotBefore = self::calcDateNotBefore($this->dateRangeUnit, $this->dateRangeNumber);
                break;
            case 'range':
                $before = $this->dateNotBefore;
                $after  = $this->dateNotAfter;

                if (null !== $before && null !== $after && $this->dateNotBefore > $this->dateNotAfter) {
                    [$before, $after] = [$after, $before];
                }

                $this->dateNotBefore = null === $before ? null : $before->format('Y-m-d');
                $this->dateNotAfter  = null === $after ? null : $after->format('Y-m-d');
        }
    }


}
