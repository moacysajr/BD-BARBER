<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\models\UserAppointment;
use App\Models\UserFavorite;
use App\Models\Barber;
use App\Models\BarberPhotos;
use App\Models\BarberServices;
use App\Models\BarberTestimonial;
use App\Models\BarberAvailability;


class BarberController extends Controller
{
    // Usuário logado
    private $loggedUser;

    // Construtor da classe, inicializa middleware e define usuário logado
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

/*
    // Função para criar barbeiros aleatórios (comentada)
    public function createRandom() {
        $array = ['error' => ''];

        for($q=0;$q<15;$q++) {
            // Nomes e sobrenomes aleatórios
            $names = ['Olecran', 'Neto', 'Atalias', 'Givaldo', 'Sibras', 'Cristiano', 'Ronaldo'];
            $lastnames = ['Jesus', 'Pops', 'Diniz', 'Sá', 'Loucura', 'Gomes'];

            // Serviços aleatórios
            $servicos = ['Corte', 'Pintura', 'Degrade', 'Manutenção'];
            $servicos2 = ['Cabelo', 'Unha', 'Cilios', 'Sobrancelhas'];

            // Depoimentos aleatórios
            $depos = [
                'Barbeiro muito midiático, deixou meu cabelo na régua.',
                'Ambiente muito pequeno, porém o corte é bem rápido.',
                'Corte barato e rápido, extremamente eficiente e o preço é bem em conta.',
                'Barbeiro legal, marca horario pelo barbershop!',
                'Barbeiro muito lento, desleixado e safado.'
            ];

            // Cria novo barbeiro
            $newBarber = new Barber();
            $newBarber->name = $names[rand(0, count($names)-1)].' '.$lastnames[rand(0, count($lastnames)-1)];
            $newBarber->avatar = rand(1, 4).'.png';
            $newBarber->stars = rand(2, 4).'.'.rand(0, 9);
            $newBarber->latitude = '-2.525400' . rand(40, 59) . rand(0, 9);
            $newBarber->longitude = '-2.525400' . rand(0, 9) . rand(0, 9) . rand(0, 9);
            $newBarber->save();

            $ns = rand(3, 6);

            // Adiciona fotos do barbeiro
            for($w=0;$w<4;$w++) {
                $newBarberPhoto = new BarberPhotos();
                $newBarberPhoto->id_barber = $newBarber->id;
                $newBarberPhoto->url = rand(1, 5).'.png';
                $newBarberPhoto->save();
            }

            // Adiciona serviços do barbeiro
            for($w=0;$w<$ns;$w++) {
                $newBarberService = new BarberServices();
                $newBarberService->id_barber = $newBarber->id;
                $newBarberService->name = $servicos[rand(0, count($servicos)-1)].' de '.$servicos2[rand(0, count($servicos2)-1)];
                $newBarberService->price = rand(1, 99).'.'.rand(0, 100);
                $newBarberService->save();
            }

            // Adiciona depoimentos do barbeiro
            for($w=0;$w<3;$w++) {
                $newBarberTestimonial = new BarberTestimonial();
                $newBarberTestimonial->id_barber = $newBarber->id;
                $newBarberTestimonial->name = $names[rand(0, count($names)-1)].' '.$lastnames[rand(0, count($lastnames)-1)];
                $newBarberTestimonial->rate = rand(2, 4).'.'.rand(0, 9);
                $newBarberTestimonial->body = $depos[rand(0, count($depos)-1)];
                $newBarberTestimonial->save();
            }

            // Adiciona disponibilidade do barbeiro
            for($e=0;$e<4;$e++) {
                $rAdd = rand(7, 10);
                $hours = [];
                for($r=0;$r<8;$r++) {
                    $time = $r + $rAdd;
                    if($time < 10) {
                        $time = '0'.$time;
                    }
                    $hours[] = $time.':00';
                }
                $newBarberAvail = new BarberAvailability();
                $newBarberAvail->id_barber = $newBarber->id;
                $newBarberAvail->weekday = $e;
                $newBarberAvail->hours = implode(',', $hours);
                $newBarberAvail->save();
            }
        }

        return $array;
    }
*/

    // Função para buscar coordenadas geográficas usando Google Maps API
    private function searchGeo($address){
        $key = env('MAPS_KEY', null);  // Chave da API do Google Maps
        $address = urlencode($address);  // Codifica o endereço para URL

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.$key;
        $ch = curl_init();  // Inicia cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);  // Executa cURL
        curl_close($ch);  // Fecha cURL

        return json_decode($res, true);  // Decodifica o resultado JSON
    }

    // Função para listar barbeiros
    public function list(Request $request) {
        $array = ['error' => ''];

        // Obtém parâmetros da requisição
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $city = $request->input('city');
        $offset = $request->input('offset', 0);  // Define offset padrão como 0

        if (!empty($city)) {
            // Busca coordenadas geográficas pelo nome da cidade
            $res = $this->searchGeo($city);

            if (count($res['results']) > 0) {
                $lat = $res['results'][0]['geometry']['location']['lat'];
                $lng = $res['results'][0]['geometry']['location']['lng'];
            }
        } elseif (!empty($lat) && !empty($lng)) {
            // Busca endereço formatado pelas coordenadas
            $res = $this->searchGeo($lat.','.$lng);

            if (count($res['results']) > 0) {
                $city = $res['results'][0]['formatted_address'];
            }
        } else {
            // Define coordenadas padrão (São Paulo)
            $lat = '-23.5630907';
            $lng = '-46.6682795';
            $city = 'São Paulo';
        }

        // Consulta barbeiros próximos ordenados por distância
        $barbers = Barber::select(Barber::raw('*, SQRT(
            POW(69.1 * (latitude - '.$lat.'), 2) +
            POW(69.1 * ('.$lng.' - longitude) * COS(latitude / 57.3), 2)) AS distance'))
            ->orderBy('distance', 'ASC')
            ->offset($offset)
            ->limit(10)
            ->get();

        // Ajusta URL do avatar
        foreach($barbers as $bkey => $bvalue) {
            $barbers[$bkey]['avatar'] = url('media/avatars/'.$barbers[$bkey]['avatar']);
        }

        $array['data'] = $barbers;
        $array['loc'] = 'São Paulo';

        return $array;
    }

        public function one ($id) {
            $array = ['error' =>''];
            $barber = Barber::find($id);

            if($barber){
                $barber['avatar'] = url('media/avatars'.$barber['avatar']);
                $barber['favorited'] = false;
                $barber['photos']= [];
                $barber['services'] = [];
                $barber['testimonials'] = [];
                $barber ['available'] = [];

                // Verificando favorito
            $cFavorite = UserFavorite::where('id_user', $this->loggedUser->id)
            ->where('id_barber', $barber->id)
            ->count();
        if($cFavorite > 0) {
            $barber['favorited'] = true;
        }


                // Pegando as fotos do Barbeiro
            $barber['photos'] = BarberPhotos::select(['id', 'url'])
            ->where('id_barber', $barber->id)
            ->get();
        foreach($barber['photos'] as $bpkey => $bpvalue) {
            $barber['photos'][$bpkey]['url'] = url('media/uploads/'.$barber['photos'][$bpkey]['url']);
        }

        // Pegando os serviços do Barbeiro
        $barber['services'] = BarberServices::select(['id', 'name', 'price'])
            ->where('id_barber', $barber->id)
            ->get();

        // Pegando os depoimentos do Barbeiro
        $barber['testimonials'] = BarberTestimonial::select(['id', 'name', 'rate', 'body'])
            ->where('id_barber', $barber->id)
            ->get();

        // Pegando disponibilidade do Barbeiro
        $availability = [];


        // Pegando a disponibilidade crua
        $avails = BarberAvailability::where('id_barber', $barber->id)->get();
        $availWeekdays = [];
        foreach($avails as $item) {
            $availWeekdays[$item['weekday']] = explode(',', $item['hours']);
        }




        // - Pegar os agendamentos dos próximos 20 dias
        $appointments = [];
        $appQuery = UserAppointment::where('id_barber', $barber->id)
            ->whereBetween('ap_datetime', [
                date('Y-m-d').' 00:00:00',
                date('Y-m-d', strtotime('+20 days')).' 23:59:59'
            ])
            ->get();
        foreach($appQuery as $appItem) {
            $appointments[] = $appItem['ap_datetime'];
        }


         // - Gerar disponibilidade real
         for($q=0;$q<20;$q++) {
            $timeItem = strtotime('+'.$q.' days');
            $weekday = date('w', $timeItem);

            if(in_array($weekday, array_keys($availWeekdays))) {
                $hours = [];

                $dayItem = date('Y-m-d', $timeItem);

                foreach($availWeekdays[$weekday] as $hourItem) {
                    $dayFormated = $dayItem.' '.$hourItem.':00';
                    if(!in_array($dayFormated, $appointments)) {
                        $hours[] = $hourItem;
                    }
                }

                if(count($hours) > 0) {
                    $availability[] = [
                        'date' => $dayItem,
                        'hours' => $hours
                    ];
                }

            }
        }



                $barber['available'] = $availability;

                $array['data'] = $barber;

            }else{
                $array['error'] = 'Barbeiro não existe';
                return $array;
            }
            return $array;


        }

        public function setAppointment($id, Request $request) {

            $array = ['error'=>''];



            $service = $request->input('service');
            $year = intval($request->input('year'));
            $month = intval($request->input('month'));
            $day = intval($request->input('day'));
            $hour = intval($request->input('hour'));




            $month =($month <10) ? '0' .$month : $month;
            $day =($day <10) ? '0' .$day : $day;
            $hour =($hour <10) ? '0' .$hour : $hour;

            // 1. verificar se o serviço do barbeiro existe
        $barberservice = BarberServices::select()
            ->where('id', $service)
            ->where('id_barber', $id)
        ->first();

        if ($barberservice){
            //2. ver se a data é real
            $apDate = $year. '-' .$month. '-'. $day. ' '.$hour. ':00:00';
            if(strtotime($apDate) >0 ){
            //3. ver se o barber tem atendendimento esse dia
                $apps = UserAppointment::select ()

                 ->where('id_barber',$id)
                 ->where('ap_datetime',$apDate)
                ->count();
                if($apps ===0 ){
                  //4.1 ver se o barber atende nesta data
                    $weekday = date('w', strtotime($apDate));
                    $avail = BarberAvailability::select()

                        ->where('id_barber', $id)
                        ->where('weekday', $weekday)
                        ->first();
                        if($avail){
                            //4.2 ver se ele atende na hr
                            $hours = explode(',', $avail['hours']);
                            if(in_array($hour.':00',$hours)){
                                //5 fazer o agendamento
                                $newApp = new UserAppointment();
                                $newApp-> id_user =$this->loggedUser->id;
                                $newApp->id_barber = $id;
                                $newApp->id_service = $service;
                                $newApp->ap_datetime= $apDate;
                                $newApp->save();


                            }else{
                                $array ['error'] = 'Barbeiro não atende neste hora';

                            }

                        }else{
                            $array['error']= "Barbeiro não atende neste dia";
                        }



                }else {
                    $array ['error'] = 'já possui agendamento neste dia/hora';

                }




            } else {
                $array ['error'] = 'Data inválida';
            }


        } else {
            $array['error'] = 'Servico inexistente!';

        }


            return $array;


        }

        public function search(Request $request) {
            $array = ['error'=>'', 'list'=>[]];

            $q = $request->input('q');

            if($q) {

                $barbers = Barber::select()
                    ->where('name', 'LIKE', '%'.$q.'%')
                ->get();

                foreach($barbers as $bkey => $barber) {
                    $barbers[$bkey]['avatar'] = url('media/avatars/'.$barbers[$bkey]['avatar']);
                }

                $array['list'] = $barbers;
            } else {
                $array['error'] = 'Digite algo para buscar';
            }

            return $array;
        }
    }
