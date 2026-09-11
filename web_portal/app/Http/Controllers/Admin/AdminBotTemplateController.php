<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotMessageTemplate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminBotTemplateController extends Controller
{
    /**
     * Muestra el panel de edición de plantillas de mensajería del Bot.
     */
    public function index(Request $request): View
    {
        $templates = BotMessageTemplate::all()->keyBy('template_key');

        // Si la tabla estuviese vacía por alguna razón, correr el seeder en caliente
        if ($templates->isEmpty()) {
            (new \Database\Seeders\BotMessageTemplateSeeder())->run();
            $templates = BotMessageTemplate::all()->keyBy('template_key');
        }

        $activeTab = $request->query('tab', 'servicios');
        if (!in_array($activeTab, ['servicios', 'sedes', 'debug_servicios', 'debug_sedes'])) {
            $activeTab = 'servicios';
        }

        return view('admin.bot_templates.index', compact('templates', 'activeTab'));
    }

    /**
     * Actualiza los parámetros de una plantilla oficial.
     */
    public function update(Request $request, BotMessageTemplate $template): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'header_text' => ['required', 'string'],
            'sub_header' => ['nullable', 'string', 'max:150'],
            'legend_text' => ['nullable', 'string'],
            'impact_statement' => ['nullable', 'string'],
            'default_signature' => ['nullable', 'string'],
            'slogan' => ['nullable', 'string', 'max:255'],
        ], [
            'title.required' => 'El título de la plantilla es obligatorio.',
            'header_text.required' => 'El encabezado institucional no puede estar vacío.',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $validated['updated_by'] = $user ? $user->full_title_name : 'Administrador';

        $template->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Plantilla '{$template->title}' actualizada exitosamente en la base de datos.",
                'template' => $template,
            ]);
        }

        return redirect()->route('admin.bot.templates.index', ['tab' => $template->template_key])
            ->with('success', "Plantilla '{$template->title}' guardada exitosamente en la base de datos.");
    }

    /**
     * Restaura la plantilla a sus valores de fábrica.
     */
    public function reset(BotMessageTemplate $template): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userName = $user ? $user->full_title_name : 'Administrador';

        $template->resetToDefault($userName);

        return redirect()->route('admin.bot.templates.index', ['tab' => $template->template_key])
            ->with('success', "La plantilla '{$template->title}' ha sido restaurada a sus valores de fábrica.");
    }

    /**
     * Endpoint para previsualización dinámica en tiempo real.
     */
    public function preview(Request $request): JsonResponse
    {
        $key = $request->input('template_key', 'servicios');
        $header = $request->input('header_text', '');
        $subHeader = $request->input('sub_header', '');
        $legend = $request->input('legend_text', '');
        $impact = $request->input('impact_statement', '');
        $signature = $request->input('default_signature', '');
        $slogan = $request->input('slogan', '');

        $now = Carbon::now('America/Caracas');
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fecha = "{$dias[$now->dayOfWeek]}, {$now->day} de {$meses[$now->month]} de {$now->year}.";
        $hora = $now->format('H:i') . '.';

        $previewText = $header . "\n";
        $previewText .= "<b>Fecha:</b> {$fecha}\n";
        $previewText .= "<b>Hora:</b> {$hora}\n\n";

        if (!empty($subHeader)) {
            $previewText .= "{$subHeader}\n\n";
        }

        if (!empty($legend)) {
            $previewText .= "{$legend}\n\n";
        }

        if ($key === 'sedes') {
            $previewText .= "━━━━━━━━━━━━\n<b>PISO 1 VALLE SECO</b>\n━━━━━━━━━━━━\n✅ - Enlace Principal\n✅ - Router de Borde\n\n";
            $previewText .= "━━━━━━━━━━━━\n<b>CIAU MORÓN</b>\n━━━━━━━━━━━━\n✅ - Enlace Datos CIAU\n✅ - Switch LAN Morón\n\n";
        } else {
            $previewText .= "━━━━━━━━━━━━\n<b>Servicios Corporativos Verificados:</b>\n━━━━━━━━━━━━\n✅ - Servidor Correo Institucional\n✅ - Servidor Web Valle Seco\n\n";
            $previewText .= "━━━━━━━━━━━━\n<b>Servicios Regionales – Carabobo Verificados:</b>\n━━━━━━━━━━━━\n✅ - Sistema Comercial Regional\n✅ - Base de Datos Operativa\n\n";
        }

        if (!empty($impact)) {
            $previewText .= "{$impact}\n\n";
        }

        if (!empty($signature)) {
            $previewText .= "{$signature}\n\n";
        }

        if (!empty($slogan)) {
            $previewText .= "{$slogan}";
        }

        return response()->json([
            'success' => true,
            'rendered_html' => nl2br($previewText),
            'raw_text' => $previewText,
        ]);
    }
}
