<?php

namespace App\Http\Livewire\ExampleLaravel;
use Illuminate\Http\Request;
use Livewire\Component;
use App\Models\Formations;
use App\Models\ContenusFormation;
use App\Exports\FormationsExport;
use Maatwebsite\Excel\Facades\Excel;

class FormationsController extends Component
{
    public function liste_formation()
    {
        $formations = Formations::paginate(4);
        return view('livewire.example-laravel.formations-management', compact('formations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'duree' => 'required|integer',
            'prix' => 'required|integer',
        ]);

        $formation = new Formations([
            'code' => $request->code,
            'nom' => $request->nom,
            'duree' => $request->duree,
            'prix' => $request->prix,
        ]);

        if ($formation->save()) {
            return response()->json(['status' => 200, 'message' => 'Formation ajoutée avec succès.']);
        } else {
            return response()->json(['status' => 400, 'message' => 'Erreur lors de l\'ajout de la formation.']);
        }
    }

    public function update(Request $request, $id)
    {
        $formation = Formations::find($id);

        if ($formation) {
            $request->validate([
                'code' => 'required|string|max:255',
                'nom' => 'required|string|max:255',
                'duree' => 'required|integer',
                'prix' => 'required|integer',
            ]);

            $formation->update($request->all());

            return response()->json(['success' => 'Formation modifiée avec succès!']);
        } else {
            return response()->json(['error' => 'Formation non trouvée'], 404);
        }
    }

    public function delete_formation($id)
    {
        $formation = Formations::find($id);

        if ($formation) {
            $formation->delete();
            return redirect()->back()->with('status', 'Formation supprimée avec succès');
        } else {
            return redirect()->back()->with('status', 'Formation non trouvée');
        }
    }

    public function export()
    {
        return Excel::download(new FormationsExport, 'formations.xlsx');
    }

    public function render()
    {
        $formations = Formations::paginate(4);
        return view('livewire.example-laravel.formations-management', compact('formations'));
    }
    public function show($id)
    {
        $formation = Formations::findOrFail($id);
        $contenus = ContenusFormation::where('formation_id', $id)->get();

        return view('livewire.example-laravel.formation-details', compact('formation', 'contenus'));
    }
}