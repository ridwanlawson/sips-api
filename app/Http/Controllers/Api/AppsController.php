<?php

namespace App\Http\Controllers\Api;

use App\Models\Field;
use App\Models\Karyawan;
use App\Models\Tph;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use Illuminate\Http\Request;

class AppsController extends Controller
{
    /**
     * @group Apps
    */
    public function employeeUpdate(){
        $datas = Field::select('FCCODE','FCNAME','PLANTINGDATE','DIVISION','HARVESTINGBASED_ABW','HECTARAGEPLANTED','OWNERSHIP','ACTIVATION','STATUS')
                    ->get();
        return new AllResource(true, 'List Data Field', $datas);
    }

    public function tphUpdate(){
        $datas = Field::select('FCCODE','FCNAME','PLANTINGDATE','DIVISION','HARVESTINGBASED_ABW','HECTARAGEPLANTED','OWNERSHIP','ACTIVATION','STATUS')
                    ->get();
        return new AllResource(true, 'List Data Field', $datas);
    }

    /**
     * @group Apps
    */
    public function attendanceGet(){
        $datas = Field::select('FCCODE','FCNAME','PLANTINGDATE','DIVISION','HARVESTINGBASED_ABW','HECTARAGEPLANTED','OWNERSHIP','ACTIVATION','STATUS')
                    ->get();
        return new AllResource(true, 'List Data Field', $datas);
    }

    /**
     * @group Apps
    */
    public function attendancePost(){
        $datas = Karyawan::whereNull('DATETERMINATE')
                        ->select('FCCODE','FCNAME','SECTIONNAME','GANGCODE','FCBA')
                        ->get();
        return new AllResource(true, 'List Data Karyawan', $datas);
    }
    
    /**
     * @group Apps
    */
    public function harvestingGet(){
        $datas = Tph::all();
        return new AllResource(true, 'List Data TPH', $datas);
    }

    /**
     * @group Apps
    */
    public function harvestingPost()
    {
        $users = User::all();
        return response()->json($users);
    }
}
