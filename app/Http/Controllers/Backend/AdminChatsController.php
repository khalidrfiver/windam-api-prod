<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FCMNotificationController;
use App\Models\AdminUserChat;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;
use App\Models\Files;
use App\Models\User;

class AdminChatsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $chat = AdminUserChat::where("role_id", "!=", 1)->get();
        return $this->reply(true, "liste des message reçu", $chat);
    }

    public function responseChat(Request $request)
    {

        if (empty(auth()->user())) {
            return $this->reply(false, "Utilisateur introuvable", null);
        }

        $updatechat = Chat::where('receiver_id', auth()->user()->id)->where('message_id', $request->message_id)->first();

        if (!$updatechat) {
            return $this->reply(false, "message introuvable", null);
        }



        DB::beginTransaction();

        try {

            $updatechat->status = 1;
            $updatechat->save();

            $chat = new Chat();
            $chat->invoices_id = $updatechat->invoices_id;
            $chat->isender_id = auth()->user()->id;
            $chat->receiver_id = $updatechat->isender_id;
            $chat->message_id = $updatechat->message_id;
            $chat->message = $request->message;
            $chat->status = 0;
            $chat->save();

            DB::commit();
            return $this->reply(true, "Message envoyer avec success", $chat);
        } catch (\Exception $e) {
            return $e;
            DB::rollback();
        }
    }

    public function sendChat(Request $request)
    {


        if (empty(auth()->user())) {
            return $this->reply(false, "Utilisateur introuvablef", null);
        }


        $chatNew = AdminUserChat::where("id", $request->receiver_id)->first();
        DB::beginTransaction();

        try {



            $chat = new Chat();
            $chat->invoices_id = null;
            $chat->sender_id = auth()->user()->id;
            $chat->receiver_id = $request->receiver_id;
            $chat->message_id = null;
            $chat->message = $request->message;
            $chat->status = 0;
            $chat->save();



            DB::commit();
            $request->request->add([
                "user_id" => $request->receiver_id,
                "body" => $request->message,
                "title" => "Nouveau message",
                "channel" => "Chat"
            ]);

/* 
            if (auth()->user()->role->role == "Admin") {
                $getChat = Chat::where('receiver_id', $request->receiver_id)->update([
                    'status' => 1
                ]);
            }  */


            (new  FCMNotificationController())->notify($request);
            return $this->reply(true, "Message envoyer avec success", $chatNew);
        } catch (\Exception $e) {
            return $e;
            DB::rollback();
        }
    }

    public function sendFile(Request $request)
    {

        // return $request;

        if (empty(auth()->user())) {
            return $this->reply(false, "Utilisateur introuvablef", null);
        }


        $chatNew = AdminUserChat::where("id", $request->receiver_id)->first();
        DB::beginTransaction();

        try {



            $chat = new Chat();
            $chat->invoices_id = null;
            $chat->sender_id = auth()->user()->id;
            $chat->receiver_id = $request->receiver_id;
            $chat->message_id = null;
            $chat->message = "Vous avez un fichier";
            $chat->status = 0;
            $chat->save();


            $file = new Files();
            $file->chat_id = $chat->id;
            $file->type = $request->type;
            $file->url = $request->url;
            $file->save();

            DB::commit();
            $request->request->add([
                "user_id" => $request->receiver_id,
                "body" => "Vous avez reçu fichier",
                "title" => "Nouveau message",
                "channel" => "Chat"
            ]);
            (new  FCMNotificationController())->notify($request);
            if ($file) {
                return $this->reply(true, "Fichier envoyer avec success", $chatNew);
            } else {
                return $this->reply(false, "Imporssible d'uploader le fichier sur le serveur", $chatNew);
            }
        } catch (\Exception $e) {
            return $e;
            DB::rollback();
        }
    }

    public function readChat($userId = User::ADMIN_ID)
    {

        if (auth()->user()->role->role == "Admin") {
            $getChat = Chat::where('receiver_id', $userId)->update([
                'status' => 1
            ]);
            return $this->reply(true, "Read", $getChat);
        }

        $getChat = Chat::where([['sender_id', auth()->user()->id], ['receiver_id', $userId]])->update([
            'status' => 1
        ]);

        return $this->reply(true, "Read", $getChat);
    }
}
