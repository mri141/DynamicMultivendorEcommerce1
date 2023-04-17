<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Mail;
use App\Models\User;
use App\Mail\SupportMailManager;

class SupportTicketController extends Controller
{
    public function index()
    {
      
       try {
            $tickets = Ticket::where('user_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(9)->map(function($data){
                return [
                      'id' => $data->id,
                      'code' => $data->code,
                      'user_id' => $data->user_id,
                      'user_name' => User::find($data->user_id)->name ?? 'deleted-user',
                      'subject' => $data->subject,
                      'details' => $data->details,
                      'files' => asset('public/uploads/ticket/'.$data->files),
                      'status' => $data->status,
                      'viewed' => $data->viewed,
                      'viewed' => $data->viewed,
                      'client_viewed' => $data->client_viewed,
                      'created_at' => $data->created_at,
                      'updated_at' => $data->updated_at,

                    ];
            });
            return response()->json($tickets);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
            ]);
        }
           
    }
    
    public function store(Request $request)
    {
        //dd();
        $ticket = new Ticket;
        $ticket->code = max(100000, (Ticket::latest()->first() != null ? Ticket::latest()->first()->code + 1 : 0)) . date('s');
        $ticket->user_id = Auth::user()->id;
        $ticket->subject = $request->subject;
        $ticket->details = $request->details;
        //image insert
        if ($request->has('image')) {
            $image = $request->file('image');
            $reImage = time() . '.' . $image->getClientOriginalExtension();
            $dest = public_path('uploads/ticket/');
            $image->move($dest, $reImage);

            // save in database
            $ticket->files = $reImage;
        }
        // $ticket->files = $request->attachments;

        if ($ticket->save()) {
            return response()->json($ticket);
        } 
    }

    public function send_support_mail_to_admin($ticket)
    {
        $array['view'] = 'emails.support';
        $array['subject'] = 'Support ticket Code is:- ' . $ticket->code;
        $array['from'] = env('MAIL_FROM_ADDRESS');
        $array['content'] = 'Hi. A ticket has been created. Please check the ticket.';
        $array['link'] = route('support_ticket.admin_show', encrypt($ticket->id));
        $array['sender'] = $ticket->user->name;
        $array['details'] = $ticket->details;

        // dd($array);
        // dd(User::where('user_type', 'admin')->first()->email);
        try {
            Mail::to(User::where('user_type', 'admin')->first()->email)
            ->queue(new SupportMailManager($array));
        } catch (\Exception $e) {
            // dd($e->getMessage());
        }
    }
    public function show($id)
    {
         try {
            $ticket = Ticket::find($id);
            $ticket->client_viewed = 1;
            $ticket->save();
           
            return response()->json([
                'ticket' => $ticket,
                'ticket_user_name' => $ticket->user->name,
                'ticket_user_avatar' => $ticket->user->avatar_original,
       
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
            ]);
        }
        
    }
    public function ticketReplies($id)
    {
         try {
            $ticket = Ticket::find($id);
            $ticket->client_viewed = 1;
            $ticket->save();
            $ticket_replies = $ticket->ticketreplies;
            return response()->json([
               'ticket_replies' => $ticket_replies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
            ]);
        }
        
    }
    public function replyTicketStore(Request $request)
    {
        
         try {
            $ticket_reply = new TicketReply;
            $ticket_reply->ticket_id = $request->ticket_id;
            $ticket_reply->user_id = $request->user_id;
            $ticket_reply->reply = $request->reply;
            
            $ticket_reply->ticket->viewed = 0;
            $ticket_reply->ticket->status = 'pending';
            $ticket_reply->ticket->save();
             if ($request->has('image')) {
            $image = $request->file('image');
            $reImage = time() . '.' . $image->getClientOriginalExtension();
            $dest = public_path('uploads/ticket/');
            $image->move($dest, $reImage);
            // save in database
            $ticket_reply->files = $reImage;
           }
            if ($ticket_reply->save()) {
                return response()->json([
                'ticket_reply' => $ticket_reply,
            ]);
            } 
           
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
            ]);
        }
        
    }
    
    
}
