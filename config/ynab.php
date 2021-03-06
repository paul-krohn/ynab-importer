<?php

/**
 * ynab.php
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
 * ynab.php

 */

declare(strict_types=1);

return [
    'version'         => '2.0.0',
    'access_token'    => env('FIREFLY_III_ACCESS_TOKEN', ''),
    'uri'             => env('FIREFLY_III_URI', ''),
    'vanity_uri'      => envNonEmpty('VANITY_URI'),
    'api_uri'         => 'https://api.youneedabudget.com/v1',
    'api_code'        => env('YNAB_API_CODE', ''),
    'minimum_version' => '5.3.0',
];
