<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotCommand;
use App\Models\BotSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBotCommandController extends Controller
{
    /**
     * Muestra la lista de comandos y configuraciones globales del bot.
     */
    public function index(Request $request): View
    {
        $category = $request->query('category', 'all');
        $search = $request->query('q', '');

        $query = BotCommand::query()->orderBy('sort_order')->orderBy('command');

        if ($category !== 'all' && !empty($category)) {
            $query->where('category', $category);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('command', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $commands = $query->get();
        $categories = BotCommand::select('category')->distinct()->pluck('category')->toArray();

        $settings = BotSetting::all()->keyBy('setting_key');
        $activeTab = $request->query('tab', 'commands');

        return view('admin.bot_commands.index', compact('commands', 'categories', 'settings', 'activeTab', 'category', 'search'));
    }

    /**
     * Actualiza la información y texto de ayuda de un comando específico.
     */
    public function update(Request $request, BotCommand $command): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'help_text' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:50'],
            'access_level' => ['required', Rule::in(['all', 'admin', 'owner'])],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ], [
            'description.required' => 'La descripción del comando es obligatoria.',
            'category.required' => 'Debe asignar una categoría al comando.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? $command->sort_order;

        $command->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Comando /{$command->command} actualizado exitosamente.",
                'command' => $command,
            ]);
        }

        return redirect()->route('admin.bot.commands.index', ['tab' => 'commands'])
            ->with('success', "Comando /{$command->command} actualizado exitosamente.");
    }

    /**
     * Activa o desactiva rápidamente un comando.
     */
    public function toggle(BotCommand $command): RedirectResponse|JsonResponse
    {
        $command->is_active = !$command->is_active;
        $command->save();

        $estado = $command->is_active ? 'activado' : 'desactivado';
        $msg = "Comando /{$command->command} {$estado} exitosamente.";

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $command->is_active,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Actualiza los mensajes del sistema y configuraciones globales del bot.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'start_header' => ['required', 'string'],
            'unknown_command' => ['required', 'string'],
            'help_general' => ['required', 'string'],
            'commands_enabled' => ['nullable'],
            'commands_locked_for_users' => ['nullable'],
        ], [
            'start_header.required' => 'El encabezado de bienvenida (/start) es obligatorio.',
            'unknown_command.required' => 'El aviso de comando desconocido es obligatorio.',
            'help_general.required' => 'El encabezado general de ayuda es obligatorio.',
        ]);

        BotSetting::set('start_header', $validated['start_header'], 'messages', 'Encabezado de Bienvenida (/start)', 'textarea');
        BotSetting::set('unknown_command', $validated['unknown_command'], 'messages', 'Aviso de Comando Desconocido', 'textarea');
        BotSetting::set('help_general', $validated['help_general'], 'messages', 'Encabezado General de Ayuda (/help)', 'textarea');

        $commandsEnabled = $request->has('commands_enabled') ? '1' : '0';
        $commandsLocked = $request->has('commands_locked_for_users') ? '1' : '0';

        BotSetting::set('commands_enabled', $commandsEnabled, 'access', 'Ejecución Global de Comandos', 'boolean');
        BotSetting::set('commands_locked_for_users', $commandsLocked, 'access', 'Bloqueo para No Administradores', 'boolean');

        return redirect()->route('admin.bot.commands.index', ['tab' => 'settings'])
            ->with('success', 'Mensajes del sistema y parámetros globales actualizados correctamente.');
    }
}
