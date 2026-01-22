<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessUnitService
{
    /**
     * Mendapatkan FCTYPE dari BUSINESSUNIT_API.GET_FCTYPE
     * 
     * @param string $fcba
     * @return string|null
     */
    public static function getFcType($fcba)
    {
        try {
            $result = DB::connection('sips_production')
                ->selectOne('SELECT BUSINESSUNIT_API.GET_FCTYPE(?) AS FCTYPE FROM DUAL', [$fcba]);

            return $result->FCTYPE ?? null;
        } catch (\Exception $e) {
            Log::error('Error getting FCTYPE: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mendapatkan FCCOMPANY dari BUSINESSUNIT_API.GET_COMPANYCODE
     * 
     * @param string $fcba
     * @return string|null
     */
    public static function getCompanyCode($fcba)
    {
        try {
            $result = DB::connection('sips_production')
                ->selectOne('SELECT BUSINESSUNIT_API.GET_COMPANYCODE(?) AS FCCOMPANY FROM DUAL', [$fcba]);

            return $result->FCCOMPANY ?? null;
        } catch (\Exception $e) {
            Log::error('Error getting FCCOMPANY: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mendapatkan FCTYPE dan FCCOMPANY sekaligus
     * 
     * @param string $fcba
     * @return array
     */
    public static function getBusinessUnitInfo($fcba)
    {
        return [
            'fctype' => self::getFcType($fcba),
            'fccompany' => self::getCompanyCode($fcba),
        ];
    }
}
