<?php

namespace App\Http\Livewire\ExampleLaravel;

use Illuminate\Http\Request;
use Livewire\Component;
use App\Models\Formations;
use App\Models\Sessions;
use App\Models\Professeur;
use App\Models\Etudiant;
use App\Models\Paiement;
use App\Models\ModePaiement;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Facades\Excel;

use App\Exports\SessionsExport;

class SessionsController extends Component
{
    public function list_session()
    {
        $sessions = Sessions::with('etudiants', 'formation')->paginate(4);
        $formations = Formations::all();
        $modes_paiement = ModePaiement::all();
        return view('livewire.example-laravel.sessions-management', compact('sessions', 'formations', 'modes_paiement'));
    }

    public function addEtudiantToSession(Request $request, $sessionId, $etudiantId)
    {
        $request->validate([
            'montant_paye' => 'required|numeric',
            'mode_paiement' => 'required|exists:mode_paiements,id',
            'date_paiement' => 'required|date',
            'prix_reel' => 'required|numeric'
        ]);

        try {
            $session = Sessions::findOrFail($sessionId);
            $etudiant = Etudiant::findOrFail($etudiantId);

            $session->etudiants()->attach($etudiantId, [
                'date_paiement' => $request->date_paiement,
            ]);

            $paiement = new Paiement([
                'etudiant_id' => $etudiantId,
                'session_id' => $sessionId,
                'prix_reel' => $request->prix_reel,
                'montant_paye' => $request->montant_paye,
                'mode_paiement_id' => $request->mode_paiement,
                'date_paiement' => $request->date_paiement,
            ]);
            $paiement->save();

            return response()->json(['success' => 'Étudiant et paiement ajoutés avec succès']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Session or student not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Error adding student to Formation: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de l\'ajout de l\'étudiant et du paiement: ' . $e->getMessage()], 500);
        }
    }

    public function getSessionContents($sessionId)
    {
        $session = Sessions::with(['etudiants' => function($query) {
            $query->withPivot('date_paiement');
        }, 'etudiants.paiements.mode', 'formation'])->find($sessionId);

        if (!$session) {
            return response()->json(['error' => 'Formation not found'], 404);
        }

        $etudiants = $session->etudiants->map(function($etudiant) use ($session) {
            $montantPaye = $etudiant->paiements->where('session_id', $session->id)->sum('montant_paye');
            $prixReel = $etudiant->paiements->where('session_id', $session->id)->first()->prix_reel ?? $session->formation->prix;
            $resteAPayer = $prixReel - $montantPaye;

            return [
                'id' => $etudiant->id,
                'nomprenom' => $etudiant->nomprenom,
                'phone' => $etudiant->phone,
                'wtsp' => $etudiant->wtsp,
                'prix_formation' => $session->formation->prix,
                'prix_reel' => $prixReel,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $resteAPayer,
                'mode_paiement' => $etudiant->paiements->where('session_id', $session->id)->first()->mode->nom ?? '',
                'date_paiement' => $etudiant->paiements->where('session_id', $session->id)->first()->date_paiement ?? '',
            ];
        });

        return response()->json([
            'etudiants' => $etudiants,
            'formation_price' => $session->formation->prix,
        ]);
    }

    public function getStudentDetails($sessionId, $etudiantId)
    {
        try {
            $etudiant = Etudiant::with(['paiements' => function ($query) use ($sessionId) {
                $query->where('session_id', $sessionId);
            }])->findOrFail($etudiantId);
            $session = Sessions::findOrFail($sessionId);

            $montantPaye = $etudiant->paiements->sum('montant_paye');
            $prixReel = $session->formation->prix;
            $resteAPayer = $prixReel - $montantPaye;

            return response()->json([
                'success' => true,
                'prix_formation' => $session->formation->prix,
                'prix_reel' => $prixReel,
                'reste_a_payer' => $resteAPayer
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Étudiant ou Formation non trouvé.'], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching student details: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la récupération des détails de l\'étudiant.'], 500);
        }
    }

    // public function addPaiement(Request $request, $sessionId)
    // {
    //     $request->validate([
    //         'etudiant_id' => 'required|exists:etudiants,id',
    //         'montant_paye' => 'required|numeric',
    //         'mode_paiement' => 'required|exists:modes_paiement,id',
    //         'date_paiement' => 'required|date',
    //     ]);

    //     try {
    //         $etudiant = Etudiant::findOrFail($request->etudiant_id);
    //         $session = Sessions::findOrFail($sessionId);

    //         $paiement = new Paiement([
    //             'etudiant_id' => $request->etudiant_id,
    //             'session_id' => $sessionId,
    //             'prix_reel' => $session->formation->prix,
    //             'montant_paye' => $request->montant_paye,
    //             'mode_paiement_id' => $request->mode_paiement,
    //             'date_paiement' => $request->date_paiement,
    //         ]);
    //         $paiement->save();

    //         return response()->json(['success' => 'Paiement ajouté avec succès']);
    //     } catch (ModelNotFoundException $e) {
    //         Log::error('Model not found: ' . $e->getMessage());
    //         return response()->json(['error' => 'Session ou Étudiant non trouvé.'], 404);
    //     } catch (\Exception $e) {
    //         Log::error('Error adding payment: ' . $e->getMessage());
    //         return response()->json(['error' => 'Erreur lors de l\'ajout du paiement: ' . $e->getMessage()], 500);
    //     }
    // }

    public function addPaiement(Request $request, $sessionId)
    {
        Log::info('Received data:', $request->all()); // Log the received data
    
        $request->validate([
            'etudiant_id' => 'required|exists:etudiants,id',
            'montant_paye' => 'required|numeric',
            'mode_paiement' => 'required|exists:modes_paiement,id',
            'date_paiement' => 'required|date',
        ]);
    
        try {
            $etudiant = Etudiant::findOrFail($request->etudiant_id);
            $session = Sessions::findOrFail($sessionId);
    
            $paiement = new Paiement([
                'etudiant_id' => $request->etudiant_id,
                'session_id' => $sessionId,
                'prix_reel' => $session->formation->prix,
                'montant_paye' => $request->montant_paye,
                'mode_paiement_id' => $request->mode_paiement,
                'date_paiement' => $request->date_paiement,
            ]);
            $paiement->save();
    
            return response()->json(['success' => 'Paiement ajouté avec succès']);
        } catch (ModelNotFoundException $e) {
            Log::error('Model not found: ' . $e->getMessage());
            return response()->json(['error' => 'Formation ou Étudiant non trouvé.'], 404);
        } catch (\Exception $e) {
            Log::error('Error adding payment: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de l\'ajout du paiement: ' . $e->getMessage()], 500);
        }
    }
    
    


    public function deleteStudentFromSession($sessionId, $etudiantId)
    {
        try {
            $session = Sessions::findOrFail($sessionId);
            $session->etudiants()->detach($etudiantId);

            Paiement::where('session_id', $sessionId)->where('etudiant_id', $etudiantId)->delete();

            return response()->json(['success' => 'Étudiant retiré de la Formation avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression de l\'étudiant: ' . $e->getMessage()], 500);
        }
    }
    public function deleteProfContent($sessionId, $profId)
{
    try {
        $session = Sessions::findOrFail($sessionId);
        $prof = Professeur::findOrFail($profId);

        if ($session && $prof) {
            $session->profs()->detach($profId);
            return response()->json(['success' => 'Professeur retiré de la Formation avec succès']);
        } else {
            return response()->json(['error' => 'Formation ou professeur non trouvé.'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Erreur lors de la suppression du professeur : ' . $e->getMessage()], 500);
    }
}



    // public function destroy($id)
    // {
    //     try {
    //         $session = Sessions::with('etudiants', 'professeurs')->findOrFail($id);
    //         if ($session->etudiants->isNotEmpty() || $session->professeurs->isNotEmpty()) {
    //             return response()->json(['status' => 400, 'message' => 'La session ne peut pas être supprimée car elle contient des étudiants ou des professeurs.']);
    //         }

    //         $session->delete();
    //         return response()->json(['success' => 'Session supprimée avec succès']);
    //     } catch (\Throwable $th) {
    //         return response()->json(['error' => $th->getMessage()], 500);
    //     }
    // }

    public function getFormationDetails($id)
    {
        $formation = Formations::find($id);
        return response()->json(['formation' => $formation]);
    }

    public function searchStudentByPhone(Request $request)
    {
        $phone = $request->phone;
        $etudiant = Etudiant::where('phone', $phone)->first();
        return response()->json(['etudiant' => $etudiant]);
    }

    public function checkStudentInSession(Request $request, $sessionId)
    {
        $etudiantId = $request->etudiant_id;
        $session = Sessions::with('etudiants')->findOrFail($sessionId);

        $isInSession = $session->etudiants->contains($etudiantId);

        return response()->json(['isInSession' => $isInSession]);
    }

    public function getProfSessionContents($id)
    {
        $session = Sessions::with('professeurs')->find($id);
        if ($session) {
            return response()->json(['prof' => $session->professeurs]);
        } else {
            return response()->json(['error' => 'Formation non trouvée'], 404);
        }
    }

    public function addProfToSession(Request $request, $sessionId)
    {
        $request->validate([
            'prof_id' => 'required|exists:professeurs,id',
        ]);

        try {
            $session = Sessions::findOrFail($sessionId);
            $session->professeurs()->attach($request->prof_id);
            return response()->json(['success' => 'Professeur ajouté à la formation avec succès']);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
            'formation_id' => 'required|exists:formations,id',
        ]);

        try {
            $session = Sessions::create($request->all());
            return response()->json(['success' => 'Formation créée avec succès', 'session' => $session]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    // public function update(Request $request, $id)
    // {
    //     $validated = $request->validate([
    //         'date_debut' => 'required|date',
    //         'date_fin' => 'required|date',
    //         'nom' => 'required|string',
    //         'formation_id' => 'required|exists:formations,id',
    //     ]);

    //     try {
    //         $session = Sessions::findOrFail($id);
    //         $session->update($validated);

    //         return response()->json(['success' => 'Session modifiée avec succès', 'session' => $session]);
    //     } catch (\Throwable $th) {
    //         return response()->json(['error' => $th->getMessage()], 500);
    //     }
    // }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
            'nom' => 'required|string',
            'formation_id' => 'required|exists:formations,id',
        ]);
    
        try {
            $session = Sessions::findOrFail($id);
    
            // Modification permise même si des étudiants ou des professeurs sont inscrits
            $session->update($validated);
            return response()->json(['success' => 'Formation modifiée avec succès', 'session' => $session]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
    
    
    public function destroy($id)
    {
        try {
            $session = Sessions::with('etudiants', 'professeurs')->findOrFail($id);
            if ($session->etudiants->isNotEmpty() || $session->professeurs->isNotEmpty()) {
                return response()->json(['status' => 400, 'message' => 'La Formation ne peut pas être supprimée car elle contient des étudiants ou des professeurs.']);
            }
    
            $session->delete();
            return response()->json(['success' => 'Formation supprimée avec succès']);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
    


    public function search6(Request $request)
    {
        if ($request->ajax()) {
            $search6 = $request->search6;
            $sessions = Sessions::where('date_debut', 'like', "%$search6%")
                ->orWhere('date_fin', 'like', "%$search6%")
                ->orWhere('nom', 'like', "%$search6%")
                ->paginate(4);

            $view = view('livewire.example-laravel.sessions-list', compact('sessions'))->render();
            return response()->json(['html' => $view]);
        }
    }


    public function render()
    {
        return $this->list_session();
    }

    public function exportSessions()
    {
        return Excel::download(new SessionsExport(), 'sessions.xlsx');
    }
}
