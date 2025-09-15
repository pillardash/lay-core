<?php

namespace BrickLayer\Lay\Libs\Primitives\Enums;

use BrickLayer\Lay\Core\LayException;

/**
 * Used inside Enums to add steroids to enums
 */
trait EnumHelper
{
    /**
     * @param self $enum
     * @return array{
     *     id: string,
     *     name: string,
     * }
     */
    private static function _assoc(self $enum) : array
    {
        return [
            "id" => $enum->name,
            "name" => str_replace("_", " ", $enum->value ??  $enum->name),
        ];
    }

    public static function to_enum(string $value, bool $throw_error = false, bool $use_value = true, bool $case_sensitive = true) : ?self
    {
        foreach (self::cases() as $enum) {
            $entry = $use_value ? ($enum->value ??  $enum->name) : $enum->name;

            if(!$case_sensitive) {
                $value = strtolower($value);
                $entry = strtolower($entry);
            }

            if($value == $entry)
                return $enum;
        }

        if($throw_error)
            LayException::throw("Value [$value] is not a valid enum entry in " . self::class, "OutOfBoundsAccess");

        return null;
    }

    public static function is_enum(string $value, bool $use_value = true) : bool
    {
        foreach (self::cases() as $enum) {
            $entry = $use_value ? ($enum->value ??  $enum->name) : $enum->name;

            if($value == $entry)
                return true;
        }

        return false;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function cases_assoc() : array
    {
        $all = [];

        foreach (self::cases() as $enum) {
            $all[] = self::_assoc($enum);
        }

        return $all;
    }

    public static function cases_assoc_api() : array
    {
        return [
            "status" => "success",
            "code" => 200,
            "message" => "Ok",
            "data" => self::cases_assoc(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function cases_row() : array
    {
        $all = [];

        foreach (self::cases() as $enum) {
            $all[] = $enum->name;
        }

        return $all;
    }

    public static function cases_row_api() : array
    {
        return [
            "status" => "success",
            "code" => 200,
            "message" => "Ok",
            "data" => self::cases_row(),
        ];
    }

    /**
     * @param 'default'|'upper'|'ucwords'|'lower'|'ucfirst' $case
     * @param bool $use_value [default: false]
     * @return string
     */
    public function stringify(string $case = "default", bool $use_value = false) : string
    {
        $str = str_replace(["_"], [" "], $use_value && $this->value ? $this->value : $this->name);

        if($case == "upper")
            return strtoupper($str);

        if($case == "lower")
            return strtolower($str);

        if($case == "ucwords")
            return ucwords(strtolower($str));

        if($case == "ucfirst")
            return ucfirst(strtolower($str));

        return $str;
    }

    public function assoc() : array
    {
        return self::_assoc($this);
    }

}