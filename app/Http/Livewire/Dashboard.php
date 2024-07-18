<?php
namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Sessions;
use App\Models\Etudiant;
use App\Models\Professeur;
use Carbon\Carbon;
use DB; // Assurez-vous d'importer le namespace DB

class Dashboard extends Component
{
    public $sessionsEnCours;
    public $sessionsTerminees;
    public $nombreEtudiants;
    public $nombreProfesseurs;
    public $etudiantsParSession;
    public $professeursParSession;
    public $etudiantsEnCours;
    public $profsEnCours;
    public $montantTotalFormationsEnCours;
    public $montantPaye;
    public $resteAPayer;
    public $montantTotalFormationsTerminees;
    public $montantPayeTermines;
    public $paiementsTermines;
    public $resteAPayerTermines;




    public function mount()
    {
        $this->sessionsEnCours = Sessions::where('date_debut', '<=', Carbon::now())
                                         ->where('date_fin', '>=', Carbon::now())
                                         ->count();

        $this->sessionsTerminees = Sessions::where('date_fin', '<', Carbon::now())->count();
        
        $this->nombreEtudiants = Etudiant::count(); // Calcul du nombre total d'étudiants
        $this->nombreProfesseurs = Professeur::count(); // Calcul du nombre total de professeurs

        $this->etudiantsParSession = Sessions::withCount('etudiants')->get(); // Nombre d'étudiants par session
        $this->professeursParSession = Sessions::withCount('professeurs')->get(); // Nombre de professeurs par session
        
        $this->profsEnCours = Professeur::whereHas('sessions', function($query) {
            $query->where('date_debut', '<=', Carbon::now())
                  ->where('date_fin', '>=', Carbon::now());
        })->count(); // Nombre d'étudiants en cours

        $this->etudiantsEnCours = Etudiant::whereHas('sessions', function($query) {
            $query->where('date_debut', '<=', Carbon::now())
                  ->where('date_fin', '>=', Carbon::now());
        })->count(); // Nombre d'étudiants en cours


        // Sous-requête pour obtenir les montants distincts par session et par étudiant
        $distinctPaiements = DB::table('paiements')
            ->join('sessions', 'paiements.session_id', '=', 'sessions.id')
            ->where('sessions.date_debut', '<=', Carbon::now())
            ->where('sessions.date_fin', '>=', Carbon::now())
            ->select('paiements.session_id', 'paiements.etudiant_id', 'paiements.prix_reel')
            ->distinct();

        // Calcul du montant total en sommant les prix_reel distincts
        $this->montantTotalFormationsEnCours = DB::table(DB::raw("({$distinctPaiements->toSql()}) as sub"))
            ->mergeBindings($distinctPaiements)
            ->sum('sub.prix_reel');

        // Calcul du montant payé et du reste à payer
        $paiements = DB::table('paiements')
                       ->join('sessions', 'paiements.session_id', '=', 'sessions.id')
                       ->where('sessions.date_debut', '<=', Carbon::now())
                       ->where('sessions.date_fin', '>=', Carbon::now())
                       ->select(DB::raw('SUM(paiements.montant_paye) as montant_paye'))
                       ->first();

        $this->montantPaye = $paiements->montant_paye ?? 0;
        $this->resteAPayer = $this->montantTotalFormationsEnCours - $this->montantPaye;



        $distinctPaiementsTermines = DB::table('paiements')
    ->join('sessions', 'paiements.session_id', '=', 'sessions.id')
    ->where('sessions.date_fin', '<', Carbon::now())
    ->select('paiements.session_id', 'paiements.etudiant_id', 'paiements.prix_reel')
    ->distinct();

    // Calcul du montant total en sommant les prix_reel distincts pour les sessions terminées
    $this->montantTotalFormationsTerminees = DB::table(DB::raw("({$distinctPaiementsTermines->toSql()}) as sub"))
        ->mergeBindings($distinctPaiementsTermines)
        ->sum('sub.prix_reel');

    // Calcul du montant payé et du reste à payer pour les sessions terminées
    $paiementsTermines = DB::table('paiements')
        ->join('sessions', 'paiements.session_id', '=', 'sessions.id')
        ->where('sessions.date_fin', '<', Carbon::now())
        ->select(DB::raw('SUM(paiements.montant_paye) as montant_paye'))
        ->first();

    $this->montantPayeTermines = $paiementsTermines->montant_paye ?? 0;
    $this->resteAPayerTermines = $this->montantTotalFormationsTerminees - $this->montantPayeTermines;

    }

    public function render()
    {
        return view('livewire.dashboard', [
            'sessionsEnCours' => $this->sessionsEnCours,
            'sessionsTerminees' => $this->sessionsTerminees,
            'nombreEtudiants' => $this->nombreEtudiants,
            'nombreProfesseurs' => $this->nombreProfesseurs,
            'etudiantsParSession' => $this->etudiantsParSession,
            'professeursParSession' => $this->professeursParSession,
            'etudiantsEnCours' => $this->etudiantsEnCours,
            'montantTotalFormationsEnCours' => $this->montantTotalFormationsEnCours,
            'montantPaye' => $this->montantPaye,
            'resteAPayer' => $this->resteAPayer,
        ]);
    }
}
