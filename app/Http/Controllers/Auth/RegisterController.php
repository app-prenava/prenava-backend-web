<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;


class RegisterController extends Controller
{
    /**
     * Show registration form for bidan
     *
     * @return \Illuminate\View\View
     */
    public function showBidanRegistrationForm()
    {
        return view('auth.register_bidan');
    }

    /**
     * Show registration form for dinkes
     *
     * @return \Illuminate\View\View
     */
    public function showDinkesRegistrationForm()
    {
        return view('auth.register_dinkes');
    }

    /**
     * Register a new bidan
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function registerBidan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nomor_str' => ['required', 'string', 'unique:users'],
            'masa_berlaku_str' => ['required', 'date'],
            'nomor_sipb' => ['required', 'string', 'unique:users'],
            'masa_berlaku_sipb' => ['required', 'date'],
            'tempat_praktik' => ['required', 'string'],
            'alamat_praktik' => ['required', 'string'],
            'telepon_tempat_praktik' => ['required', 'string'],
            'spesialisasi' => ['required', 'string'],
            'nik' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'bidan',
            'nomor_str' => $request->nomor_str,
            'masa_berlaku_str' => $request->masa_berlaku_str,
            'nomor_sipb' => $request->nomor_sipb,
            'masa_berlaku_sipb' => $request->masa_berlaku_sipb,
            'tempat_praktik' => $request->tempat_praktik,
            'alamat_praktik' => $request->alamat_praktik,
            'telepon_tempat_praktik' => $request->telepon_tempat_praktik,
            'spesialisasi' => $request->spesialisasi,
            'nik' => $request->nik,
        ]);

        event(new Registered($user));

        auth()->login($user);

        return redirect()->route('bidan.dashboard');
    }

    /**
     * Register a new dinkes
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function registerDinkes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nama_dinas' => ['required', 'string'],
            'alamat_kantor' => ['required', 'string'],
            'website' => ['nullable', 'string', 'url'],
            'nama_admin' => ['required', 'string'],
            'nip' => ['required', 'string'],
            'jabatan' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Handle file uploads - store to local public disk
        $logoPath = null;
        $fotoKtpPath = null;

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        if ($request->hasFile('foto_ktp') && $request->file('foto_ktp')->isValid()) {
            $fotoKtpPath = $request->file('foto_ktp')->store('ktp', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'dinkes',
            'nama_dinas' => $request->nama_dinas,
            'alamat_kantor' => $request->alamat_kantor,
            'website' => $request->website,
            'logo' => $logoPath,
            'nama_admin' => $request->nama_admin,
            'nip' => $request->nip,
            'jabatan' => $request->jabatan,
            'foto_ktp' => $fotoKtpPath,
            'admin_id' => Auth::id(), // ID admin 
        ]);

        event(new Registered($user));

        auth()->login($user);

        return redirect()->route('dinkes.dashboardd');
    }
}