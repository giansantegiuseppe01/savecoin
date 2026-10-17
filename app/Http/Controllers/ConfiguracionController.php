<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $config = Configuracion::getConfig();

        return view('configuracion.index', compact('config'));
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre_empresa' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:png|max:2048',
                'rif' => 'nullable|string|max:50',
                'tasa_cambio' => 'required|numeric|min:0.0001',
                'eslogan' => 'nullable|string|max:255',
                'direccion' => 'nullable|string|max:500',
                'telefono' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
            ]);

            $config = Configuracion::getConfig();

            // Manejar subida de logo
            if ($request->hasFile('logo')) {
                $logo = $request->file('logo');
                $logoName = 'logo_'.time().'.png';
                $logo->move(public_path('uploads/logos'), $logoName);
                $validated['logo'] = 'uploads/logos/'.$logoName;
            } else {
                // Mantener logo existente si no se sube uno nuevo
                unset($validated['logo']);
            }

            $config->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar configuración: '.$e->getMessage(),
            ], 500);
        }
    }
}
