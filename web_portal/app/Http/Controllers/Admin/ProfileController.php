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

        // 1. Manejo exclusivo de avatar (desde la tarjeta izquierda)
        if ($request->has('avatar_only') || (!$request->filled('name') && !$request->filled('cedula') && !$request->filled('atit_profile_only') && ($request->hasFile('avatar') || $request->boolean('remove_avatar')))) {
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

            return redirect()->route('admin.profile.show')->with('info', 'No se realizaron cambios en la foto de perfil.');
        }

        // 2. Actualización de Ficha Institucional ATIT (válida para usuarios LDAP y Locales)
        if ($request->has('atit_profile_only')) {
            $validated = $request->validate([
                'academic_title' => ['nullable', 'string', 'max:50'],
                'cedula' => ['nullable', 'string', 'max:30'],
                'personal_number' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:50'],
            ], [
                'academic_title.max' => 'El título o grado no puede exceder 50 caracteres.',
                'cedula.max' => 'La cédula no puede superar 30 caracteres.',
                'personal_number.max' => 'El número de personal no puede superar 50 caracteres.',
                'phone.max' => 'El teléfono no puede superar 50 caracteres.',
            ]);

            $user->academic_title = $validated['academic_title'] ?? null;
            $user->cedula = $validated['cedula'] ?? null;
            $user->personal_number = $validated['personal_number'] ?? null;
            $user->phone = $validated['phone'] ?? null;
            $user->save();

            return redirect()->route('admin.profile.show')->with('success', 'Ficha Institucional ATIT actualizada exitosamente.');
        }

        // 3. Actualización de Cuenta Local Completa
        if (!$isLdap) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'academic_title' => ['nullable', 'string', 'max:50'],
                'cedula' => ['nullable', 'string', 'max:30'],
                'personal_number' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:50'],
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
            $user->academic_title = $validated['academic_title'] ?? null;
            $user->cedula = $validated['cedula'] ?? null;
            $user->personal_number = $validated['personal_number'] ?? null;
            $user->phone = $validated['phone'] ?? null;

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

        return redirect()->route('admin.profile.show');
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
