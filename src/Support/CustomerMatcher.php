<?php

namespace VentureDrake\LaravelCrm\Support;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Phone;

/**
 * Pencocok pelanggan (Person / Organization) supaya tidak ada data ganda.
 *
 * Nama & nomor HP bisa terenkripsi di database (laravel-crm.encrypt_db_fields), jadi pencocokan
 * tidak boleh mengandalkan "WHERE kolom = nilai" saja: kandidat diambil lalu dibandingkan di PHP
 * lewat accessor yang sudah mendekripsi. Semua query otomatis ikut filter tenant.
 *
 * Aturan:
 *  1. Nomor HP sama (format +62 / 0 / spasi / strip diabaikan) = pelanggan yang sama.
 *  2. Kalau tidak ada HP yang cocok, nama sama persis (huruf besar/kecil diabaikan) = pelanggan yang sama,
 *     KECUALI HP yang dikirim ada dan pemilik nama itu sudah punya HP lain (itu orang berbeda, namanya kebetulan sama).
 */
class CustomerMatcher
{
    public const WALK_IN_NAME = 'Pelanggan Umum';

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);
        }

        $digits = ltrim($digits, '0');

        // terlalu pendek = bukan identitas yang layak dipakai untuk mencocokkan
        return strlen($digits) >= 6 ? $digits : null;
    }

    public static function normalizeName(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
    }

    protected static function encrypted(): bool
    {
        return (bool) config('laravel-crm.encrypt_db_fields');
    }

    // ------------------------------------------------------------------ Person

    public static function findPersonByPhone(?string $phone, ?int $exceptId = null): ?Person
    {
        $needle = static::normalizePhone($phone);

        if (! $needle) {
            return null;
        }

        $query = Phone::query()->where('phoneable_type', (new Person)->getMorphClass());

        if (! static::encrypted()) {
            $query->where('number', 'like', '%'.substr($needle, -6).'%');
        }

        foreach ($query->get() as $row) {
            if (static::normalizePhone($row->number) !== $needle) {
                continue;
            }

            if ($exceptId && (int) $row->phoneable_id === $exceptId) {
                continue;
            }

            if ($person = Person::find($row->phoneable_id)) {
                return $person;
            }
        }

        return null;
    }

    /** @return \Illuminate\Support\Collection<int, Person> */
    public static function findPeopleByName(?string $name, ?int $exceptId = null)
    {
        $needle = static::normalizeName($name);

        if ($needle === '') {
            return collect();
        }

        $query = Person::query()->select(['id', 'first_name', 'middle_name', 'last_name']);

        if (! static::encrypted()) {
            $first = explode(' ', $needle)[0];
            $query->where('first_name', 'like', $first.'%');
        }

        return $query->get()->filter(function (Person $p) use ($needle, $exceptId) {
            if ($exceptId && $p->id === $exceptId) {
                return false;
            }

            return static::normalizeName($p->first_name.' '.$p->last_name) === $needle;
        })->values();
    }

    public static function findPersonByName(?string $name): ?Person
    {
        $hit = static::findPeopleByName($name)->first();

        return $hit ? Person::find($hit->id) : null;
    }

    public static function matchPerson(?string $name, ?string $phone = null, ?int $exceptId = null): ?Person
    {
        if ($person = static::findPersonByPhone($phone, $exceptId)) {
            return $person;
        }

        $hasPhone = static::normalizePhone($phone) !== null;

        foreach (static::findPeopleByName($name, $exceptId) as $candidate) {
            $person = Person::find($candidate->id);

            if (! $person) {
                continue;
            }

            if ($hasPhone && $person->phones()->exists()) {
                continue; // nama sama tapi punya nomor lain = orang lain
            }

            return $person;
        }

        return null;
    }

    // ------------------------------------------------------------ Organization

    public static function matchOrganization(?string $name, ?int $exceptId = null): ?Organization
    {
        $needle = static::normalizeName($name);

        if ($needle === '') {
            return null;
        }

        $query = Organization::query()->select(['id', 'name']);

        if (! static::encrypted()) {
            $query->where('name', 'like', explode(' ', $needle)[0].'%');
        }

        $hit = $query->get()->first(function (Organization $o) use ($needle, $exceptId) {
            return (! $exceptId || $o->id !== $exceptId) && static::normalizeName($o->name) === $needle;
        });

        return $hit ? Organization::find($hit->id) : null;
    }

    // -------------------------------------------------- phone / email kontak

    /** Tambahkan nomor HP kalau belum ada pada kontak ini. */
    public static function attachPhone($owner, ?string $phone): void
    {
        $needle = static::normalizePhone($phone);

        if (! $owner || ! $needle) {
            return;
        }

        foreach ($owner->phones()->get() as $existing) {
            if (static::normalizePhone($existing->number) === $needle) {
                return;
            }
        }

        $owner->phones()->create([
            'external_id' => (string) Uuid::uuid4(),
            'number' => trim($phone),
            'type' => 'mobile',
            'primary' => $owner->phones()->where('primary', 1)->exists() ? 0 : 1,
        ]);
    }

    /** Ganti nomor HP utama (kosong = hapus). */
    public static function setPrimaryPhone($owner, ?string $phone): void
    {
        $phone = trim((string) $phone);
        $primary = $owner->phones()->where('primary', 1)->first() ?? $owner->phones()->first();

        if ($phone === '') {
            $primary?->delete();

            return;
        }

        if ($primary) {
            $primary->update(['number' => $phone, 'primary' => 1]);

            return;
        }

        $owner->phones()->create([
            'external_id' => (string) Uuid::uuid4(),
            'number' => $phone,
            'type' => 'mobile',
            'primary' => 1,
        ]);
    }

    /** Ganti email utama (kosong = hapus). */
    public static function setPrimaryEmail($owner, ?string $address): void
    {
        $address = trim((string) $address);
        $primary = $owner->emails()->where('primary', 1)->first() ?? $owner->emails()->first();

        if ($address === '') {
            $primary?->delete();

            return;
        }

        if ($primary) {
            $primary->update(['address' => $address, 'primary' => 1]);

            return;
        }

        $owner->emails()->create([
            'external_id' => (string) Uuid::uuid4(),
            'address' => $address,
            'type' => 'work',
            'primary' => 1,
        ]);
    }

    public static function primaryPhoneNumber($owner): ?string
    {
        $row = $owner->phones->firstWhere('primary', true) ?? $owner->phones->first();

        return $row?->number;
    }

    public static function primaryEmailAddress($owner): ?string
    {
        $row = $owner->emails->firstWhere('primary', true) ?? $owner->emails->first();

        return $row?->address;
    }
}
