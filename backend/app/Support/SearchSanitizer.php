<?php

namespace App\Support;

/**
 * Escapa caracteres especiais de LIKE (% e _) em termos de busca
 * para evitar LIKE injection em queries SQL.
 */
class SearchSanitizer
{
    /**
     * Escapa wildcards do SQL LIKE no termo de busca.
     * Uso: $term = SearchSanitizer::escapeLike($request->search);
     *      $query->where('name', 'like', "%{$term}%");
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    /**
     * Retorna o termo já envolvido com % para LIKE contains.
     * Uso: $query->where('name', 'like', SearchSanitizer::contains($request->search));
     */
    public static function contains(string $value): string
    {
        return '%'.static::escapeLike($value).'%';
    }
}
