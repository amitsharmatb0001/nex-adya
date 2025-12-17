<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PasswordStrengthController extends Controller
{
    /**
     * POST /api/v1/auth/password-strength
     */
    public function check(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $pwd = $request->password;

        $requirements = [
            'min_length'  => strlen($pwd) >= 12,
            'mixed_case'  => preg_match('/[A-Z]/', $pwd) && preg_match('/[a-z]/', $pwd),
            'number'      => preg_match('/[0-9]/', $pwd),
            'symbol'      => preg_match('/[\W_]/', $pwd),
            'not_common'  => !in_array(strtolower($pwd), ['password','123456','qwerty','admin','welcome']),
        ];

        $score = count(array_filter($requirements));
        $strength = match (true) {
            $score <= 2 => 'weak',
            $score == 3 => 'medium',
            $score == 4 => 'strong',
            $score == 5 => 'very_strong',
        };

        return response()->json([
            'ok' => true,
            'strength' => $strength,
            'score' => $score,
            'requirements' => $requirements,
        ]);
    }
}
