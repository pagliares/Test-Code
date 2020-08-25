<?php

namespace App\Http\Controllers;

use App\Disciplina;
use App\Usuario;
use App\Projeto;
use App\Resposta;
use App\Usuariosdisciplinas;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Session;
use DB;

class DisciplinaAlunoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function indexNota(){
        $notas = DB::table('respostas')
                        ->join('provas','respostas.idProva','=','provas.id')
                        ->join('projetos','provas.idProjeto','=','projetos.id')
                        ->join('disciplinas','projetos.idDisciplina','=','disciplinas.id')
                        ->select('*')
                        ->orderBy('disciplinas.nome')
                        ->where('respostas.idAluno','=',auth()->user()->id)
                        ->get();
        
        $disciplinas = DB::table('disciplinas')
                        ->join('usuariosdisciplinas','disciplinas.id','=','usuariosdisciplinas.idDisciplina')
                        ->join('usuarios','usuariosdisciplinas.idUsuario','=','usuarios.id')
                        ->select('disciplinas.*')
                        ->where('usuarios.id','=',auth()->user()->id)
                        ->get();
        
        return view('aluno/notas',compact('notas','disciplinas'));
    }

    public function index($disciplina)
    {
        $provas = DB::table('provas')
                    ->join('projetos','provas.idProjeto','=','projetos.id')
                    ->join('disciplinas','projetos.idDisciplina','=','disciplinas.id')
                    ->select('provas.*')
                    ->where('disciplinas.nome','=',$disciplina)
                    ->get();
        
        return view('/aluno/disciplina', compact('disciplina','provas'));
    }

    public function baixarProva($disciplina,$prova_nome){
        $prova= DB::table('provas')
                            ->select('provas.*')
                            ->where('provas.nomeProva','=',$prova_nome)
                            ->first();
        

        $path = public_path($prova->featured);
        $name = "TestCode_".$prova->nomeProva.".zip";
        $headers = [
            'Content-Type : application/octet-stream',
        ];
        return response()->download($path, $name,$headers);
    }
    
    public function indexupload($disciplina){
        return view('/professor/upload', compact('disciplina'));
    }

    public function mandarResposta(Request $request){
        $disciplina = $request->disciplina;
        $nomeProva = $request->nomeProva;
        $prova = DB::table('provas')
        ->select('provas.*')
        ->where('provas.nomeProva','=',$nomeProva)
        ->first();
        $existe = DB::table('respostas')
                    ->select('*')
                    ->where('idProva','=',$prova->id)
                    ->get();

        foreach($existe as $respostas){
            if($respostas->idAluno==auth()->user()->id){
                Session::flash('erro', 'Você já enviou a resposta para está prova.');
                return redirect()->back();
            }
        }
        $this->validate($request, [
            'featured' => 'required|mimes:zip'
        ]);

        $searchzip = '.zip';

        $featured = $request->featured;
        $featured_new_name = $featured->getClientOriginalName();
        $featured->move('uploads/'.$disciplina.'/respostas/'. auth()->user()->nome .'/'. $nomeProva.'/',$featured_new_name);

        $prova = DB::table('provas')
            ->select('provas.*')
            ->where('provas.nomeProva','=',$nomeProva)
            ->first();
        $projeto = DB::table('projetos')
            ->select('projetos.*')
            ->where('projetos.id','=',$prova->idProjeto)
            ->first();
        
        exec("unzip uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/$featured_new_name -d uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/");
        exec("ls uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/",$out);
        
        if(strstr($out[0],$searchzip)){
            $nomeResposta = $out[1];
        }else{
            $nomeResposta = $out[0];
        }

        exec("ls uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/$nomeResposta",$out3);
        $nomeProjeto = $out3[0];
        exec("cp -r $projeto->featured/src/test uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/$nomeResposta/$nomeProjeto/src/");
        exec("cp -r $projeto->featured/pom.xml uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/$nomeResposta/$nomeProjeto");
        exec("mvn test -f uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva/$nomeResposta/$nomeProjeto",$out2);
        
        $total=0;
        $search = 'Tests run';
        foreach($out2 as $linha){
            if(strstr($linha, $search)){
                $arrayRespostas = explode(',',$linha);
                foreach($arrayRespostas as $resposta){
                    if(strstr($resposta, 'Tests run')){
                        $total = preg_replace("/[^0-9]/", "", $resposta);
                    }
                    if(strstr($resposta, 'Failures')){
                        $erros = preg_replace("/[^0-9]/", "", $resposta);
                    }
                }
            }
        }
        if($total!=0){
            $nota = 10-((10/$total)*$erros);
        }else{
            exec("rm -r -d uploads/$disciplina/respostas/".auth()->user()->nome."/$nomeProva");
            Session::flash('erro', 'Houve um erro no envio da sua prova, por favor verifique se seu envio está conforme o necessário.');
            return redirect()->back();
        }


        $resposta = Resposta::create([
            'featured' => 'uploads/'.$disciplina.'/respostas/'. auth()->user()->nome .'/'. $nomeProva.'/',
            'idProva' => $prova->id,
            'idAluno' => auth()->user()->id,
            'nota' => $nota,
        ]);

        Session::flash('status', 'Prova enviada com sucesso.');
        $notas = DB::table('respostas')
                        ->join('provas','respostas.idProva','=','provas.id')
                        ->join('projetos','provas.idProjeto','=','projetos.id')
                        ->join('disciplinas','projetos.idDisciplina','=','disciplinas.id')
                        ->select('*')
                        ->orderBy('disciplinas.nome')
                        ->where('respostas.idAluno','=',auth()->user()->id)
                        ->get();
        $disciplinas = DB::table('disciplinas')
                        ->join('usuariosdisciplinas','disciplinas.id','=','usuariosdisciplinas.idDisciplina')
                        ->join('usuarios','usuariosdisciplinas.idUsuario','=','usuarios.id')
                        ->select('disciplinas.*')
                        ->where('usuarios.id','=',auth()->user()->id)
                        ->get();
    
        return view('aluno/notas',compact('notas','disciplinas'));
    }

    public function indexEnviarResposta($disciplina,$prova_nome){
        return view('/aluno/upload', compact('disciplina','prova_nome'));
    }

    public function indexNotasProfessor($disciplina){

        $notas = DB::table('respostas')
                        ->join('provas','respostas.idProva','=','provas.id')
                        ->join('projetos','provas.idProjeto','=','projetos.id')
                        ->join('disciplinas','projetos.idDisciplina','=','disciplinas.id')
                        ->join('usuarios','respostas.idAluno','=','usuarios.id')
                        ->select('*')
                        ->where('disciplinas.nome','=',$disciplina)
                        ->get();
        
        return view('/professor/notas', compact('disciplina','notas'));
    }
    
}