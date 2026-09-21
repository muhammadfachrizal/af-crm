<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class ApiController extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected int $defaultPerPage = 25;

    protected int $maxPerPage = 100;

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', $this->defaultPerPage);

        if ($perPage < 1) {
            $perPage = $this->defaultPerPage;
        }

        return min($perPage, $this->maxPerPage);
    }

    /**
     * Apply a `?sort=field` / `?sort=-field` query parameter to a builder.
     *
     * @param  array<int, string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default = '-created_at'): Builder
    {
        $sort = (string) $request->query('sort', $default);

        if ($sort === '') {
            $sort = $default;
        }

        $direction = 'asc';
        $column = $sort;

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $column = substr($sort, 1);
        }

        if (! in_array($column, $allowed, true)) {
            return $query;
        }

        return $query->orderBy($column, $direction);
    }

    protected function applySearch(
        Builder $query,
        Request $request,
        array $columns
    ): Builder 
    {
        if (! $request->filled('search')) {
            return $query;
        }

        $search = $request->input('search');

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);

                    $q->orWhereHas($relation, function ($relationQuery) use ($field, $search) {
                        $relationQuery->where(
                            $field,
                            'like',
                            "%{$search}%"
                        );
                    });
                } else {
                    $q->orWhere(
                        $column,
                        'like',
                        "%{$search}%"
                    );
                }
            }
        });
    }

    protected function searchAndPaginate(
        Builder $query,
        Request $request,
        int $perPage,
        array $searchable
    ): LengthAwarePaginator 
    {
        $search = trim((string) $request->input('search', ''));

        if ($search === '') {
            return $query
                ->paginate($perPage)
                ->withQueryString();
        }

        $search = strtolower($search);

        $records = $query->get()->filter(function ($record) use ($search, $searchable) {
            foreach ($searchable as $field) {
                $value = $record;

                foreach (explode('.', $field) as $segment) {
                    if ($value === null) {
                        break;
                    }

                    $value = $value->{$segment};
                }

                if (
                    $value !== null &&
                    Str::contains(
                        strtolower((string) $value),
                        $search
                    )
                ) {
                    return true;
                }
            }

            return false;
        })->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;

        $items = $records->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $records->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );
    }

    protected function hasEncryptedSearchableFields($model, array $searchable): bool
    {
        $encryptable = $model->getEncryptable();

        foreach ($searchable as $field) {
            if (str_contains($field, '.')) {
                [$relation, $field] = explode('.', $field, 2);

                $relatedModel = $model->{$relation}();

                if (
                    in_array($field, $relatedModel->getModel()->getEncryptable(), true)
                ) {
                    return true;
                }
            } elseif (in_array($field, $encryptable, true)) {
                return true;
            }
        }

        return false;
    }

    protected function searchAndPaginateAutomatically(
        Builder $query,
        Request $request,
        int $perPage,
        array $searchable
    ): LengthAwarePaginator {
        $model = $query->getModel();

        if (
            ! $request->filled('search') ||
            ! $this->hasEncryptedSearchableFields($model, $searchable)
        ) {
            return $query
                ->paginate($perPage)
                ->withQueryString();
        }

        return $this->searchAndPaginate(
            $query,
            $request,
            $perPage,
            $searchable
        );
    }
}
