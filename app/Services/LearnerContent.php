<?php

namespace App\Services;

final class LearnerContent
{
    public static function useLuganda(object $record, array $translations): object
    {
        foreach ($translations as $source => $translated) {
            if (isset($record->{$translated}) && $record->{$translated} !== '') {
                $record->{$source} = $record->{$translated};
            }
        }

        return $record;
    }
}
