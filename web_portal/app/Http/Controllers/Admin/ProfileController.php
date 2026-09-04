<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Muestra la vista de perfil del usuario en sesión
     */
    public function show(): View
    {
        $user = Auth::user();
        return view('admin.profile.show', compact('user'));
    }

    /**
     * Actualiza la información del perfil y/o foto de avatar
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isLdap = $user->isLdapUser();

        // 1. SI ES USUARIO LDAP: SOLO PUEDE MODIFICAR SU FOTO DE PERFIL
        if ($isLdap) {
            $request->validate([
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
                'remove_avatar' => ['nullable', 'boolean'],
            ], [
                'avatar.image' => 'El archivo seleccionado debe ser una imagen válida.',
                'avatar.mimes' => 'La foto de perfil debe tener formato JPEG, PNG, JPG o WEBP.',
                'avatar.max' => 'La fotografía no debe exceder 2 MB de tamaño.',
            ]);

            // Eliminación explícita de foto de perfil
            if ($request->boolean('remove_avatar')) {
                $this->deleteAvatarFile($user->avatar);
                $user->avatar = null;
                $user->save();
                return redirect()->route('admin.profile.show')->with('success', 'Foto de perfil eliminada. Se ha restablecido el avatar por defecto.');
            }

            // Subida de nueva fotografía
            if ($request->hasFile('avatar')) {
                $this->deleteAvatarFile($user->avatar);
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar = $path;
                $user->save();
                return redirect()->route('admin.profile.show')->with('success', 'Foto de perfil actualizada exitosamente.');
            }

            return redirect()->route('admin.profile.show')->with('info', 'No se realizaron cambios en la foto de perfil.');
        }

        // 2. SI ES CUENTA LOCAL
        // Caso A: Petición desde la tarjeta de foto de perfil (solo avatar)
        if (!$request->filled('name') && ($request->hasFile('avatar') || $request->boolean('remove_avatar'))) {
            $request->validate([
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
                'remove_avatar' => ['nullable', 'boolean'],
            ], [
                'avatar.image' => 'El archivo seleccionado debe ser una imagen válida.',
                'avatar.mimes' => 'La foto de perfil debe tener formato JPEG, PNG, JPG o WEBP.',
                'avatar.max' => 'La fotografía no debe exceder 2 MB de tamaño.',
            ]);

            if ($request->boolean('remove_avatar')) {
                $this->deleteAvatarFile($user->avatar);
                $user->avatar = null;
                $user->save();
                return redirect()->route('admin.profile.show')->with('success', 'Foto de perfil eliminada. Se ha restablecido el avatar por defecto.');
            }

            if ($request->hasFile('avatar')) {
                $this->deleteAvatarFile($user->avatar);
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar = $path;
                $user->save();
                return redirect()->route('admin.profile.show')->with('success', 'Foto de perfil actualizada exitosamente.');
            }
        }

        // Caso B: Formulario de actualización de datos de cuenta local
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado por otro usuario.',
            'password.min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'avatar.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'avatar.mimes' => 'La foto de perfil debe tener formato JPEG, PNG, JPG o WEBP.',
            'avatar.max' => 'La fotografía no debe exceder 2 MB de tamaño.',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Actualizar contraseña si fue ingresada
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Manejo de avatar
        if ($request->boolean('remove_avatar')) {
            $this->deleteAvatarFile($user->avatar);
            $user->avatar = null;
        } elseif ($request->hasFile('avatar')) {
            $this->deleteAvatarFile($user->avatar);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return redirect()->route('admin.profile.show')->with('success', 'Perfil actualizado exitosamente.');
    }

    /**
     * Elimina el archivo de avatar físicamente del disco si existe
     */
    protected function deleteAvatarFile(?string $avatarPath): void
    {
        if (empty($avatarPath)) {
            return;
        }

        if (Storage::disk('public')->exists($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
        }

        $fullPath = public_path('storage/' . $avatarPath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
