<?php

declare(strict_types=1);

use App\Service\DoctorService;
use App\Service\UserService;
use Illuminate\Support\Facades\Auth;

function currentUserId(): int
{
    return (int) Auth::id();
}

function currentHospitalId(): int
{
    return (int) (Auth::user()?->hospitalid);
}

function currentPage(): int
{
    return max(1, (int) request()->input('p', 1));
}

function timenow(): int
{
    return time();
}

function formatDate(string $format = 'Ymd', int $timestamp = 0): string
{
    return date($format, $timestamp ?: time());
}

function getOption(string $key, mixed $default = null): mixed
{
    return config('remeo.options.'.$key.'.value', $default);
}

/**
 * @param  null|array<string,mixed>  $user
 */
function isAdministrator(?array $user = null): bool
{
    $user ??= UserService::user();

    return ! empty($user['id']) && in_array((int) $user['id'], config('remeo.super_users', []), true);
}

/**
 * @param  null|array<string,mixed>  $user
 */
function isOperator(?array $user = null): bool
{
    $user ??= UserService::user();

    return isAdministrator($user) || (! empty($user['mobile']) && in_array($user['mobile'], config('remeo.hospital.operator', []), true));
}

/**
 * @param  null|array<string,mixed>  $user
 */
function isViewer(?array $user = null): bool
{
    $user ??= UserService::user();

    return isAdministrator($user) || (! empty($user['mobile']) && in_array($user['mobile'], config('remeo.hospital.viewer', []), true));
}

/**
 * @param  null|array<string,mixed>  $user
 */
function canDownload(?array $user = null): bool
{
    $user ??= UserService::user();

    return isAdministrator($user) || (! empty($user['id']) && in_array('download.patient', app(DoctorService::class)->getUserPermissions($user['id']), true));
}

function yes(): int
{
    return 1;
}

function getYesNoText(?string $value = null): string
{
    return $value == 2 ? '否' : '是';
}

function getGenderText(?string $gender = null): string
{
    return $gender == 2 ? '女' : '男';
}

function isFemale(mixed $gender): bool
{
    return $gender == 2;
}
