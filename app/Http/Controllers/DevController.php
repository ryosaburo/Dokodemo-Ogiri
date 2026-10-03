<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** 開発用: テストユーザーの作成(localのみ。routes側で制限) */
class DevController extends Controller
{
    public function createUsers(Request $request)
    {
        $data = $request->validate(['names' => ['required', 'string', 'max:200']]);

        $created = collect(preg_split('/[\s,、]+/u', $data['names'], -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($name) => User::create([
                'name' => Str::limit($name, 20, ''),
                'email' => Str::uuid().'@guest.local',
                'password' => Str::random(32),
            ]));

        return redirect()->route('home', array_filter(['as' => $request->input('as')]));
    }
}
