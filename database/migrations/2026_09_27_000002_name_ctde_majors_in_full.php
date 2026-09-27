<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The CTDE program list used to carry "Bachelor of Technical-Vocational
 * Teacher Education, Major in:" as a program of its own, with each major
 * listed after it as if it were a separate program. The list now names each
 * major in full; bluebooks filed under a bare major follow it.
 */
return new class extends Migration
{
    private const DEGREE = 'Bachelor of Technical-Vocational Teacher Education, Major in ';
    private const MAJORS = ['Food Service Management', 'Electronics Technology', 'Fish Processing'];

    public function up()
    {
        foreach (self::MAJORS as $major) {
            DB::table('bluebooks')->where('department', 'CTDE')->where('program', $major)
                ->update(['program' => self::DEGREE . $major]);
        }
    }

    public function down()
    {
        foreach (self::MAJORS as $major) {
            DB::table('bluebooks')->where('department', 'CTDE')->where('program', self::DEGREE . $major)
                ->update(['program' => $major]);
        }
    }
};
