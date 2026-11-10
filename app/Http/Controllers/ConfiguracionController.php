<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\FormaPago;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $config = Configuracion::getConfig();

        $formasPago = FormaPago::orderBy('orden')->orderBy('id')->get();

        return view('configuracion.index', compact('config', 'formasPago'));
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

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar configuración.',
            ], 500);
        }
    }

    private function validarFormaPago(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:100',
            'datos' => 'required|string|max:1000',
            'activo' => 'nullable|boolean',
        ]);
    }

    public function storeFormaPago(Request $request)
    {
        $datos = $this->validarFormaPago($request);
        $datos['activo'] = $request->boolean('activo', true);
        $datos['orden'] = (int) FormaPago::max('orden') + 1;

        return response()->json(['success' => true, 'forma_pago' => FormaPago::create($datos)]);
    }

    public function updateFormaPago(Request $request, FormaPago $formaPago)
    {
        $datos = $this->validarFormaPago($request);
        $datos['activo'] = $request->boolean('activo', true);
        $formaPago->update($datos);

        return response()->json(['success' => true, 'forma_pago' => $formaPago]);
    }

    public function destroyFormaPago(FormaPago $formaPago)
    {
        $formaPago->delete();

        return response()->json(['success' => true]);
    }
}
