<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use App\Settings\TelegramSettings;
use App\Telegram\Context\AbstractContext;
use App\Telegram\Queries\AbstractQuery;
use Illuminate\Http\Request;
use Telegram\Bot\Events\UpdateEvent;

class TelegramApiController extends Controller
{
    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request, $randomString)
    {
        $telegramSetting = new TelegramSettings();
        if ($randomString !== $telegramSetting->random_string) {
            abort(404);
        }

        $telegram = (new TelegramService())->telegram;
        try {
            // handle call back query
            $telegram->on('callback_query.photo', function (UpdateEvent $event) {
                $action = AbstractQuery::match($event->update->callbackQuery->data);

                if ($action) {
                    $action = new $action();
                    $action->handle($event);
                    return response()
                        ->json([
                            'status' => true,
                            'error' => null
                        ]);;
                }
                echo 'No matched action';
                return $event->telegram->answerCallbackQuery([
                    'callback_query_id' => $event->update->callbackQuery->id,
                    'text' => 'Unfortunately, there is no matched action to respond to this callback',
                ]);
            });
            $telegram->on('callback_query.text', function (UpdateEvent $event) {
                $action = AbstractQuery::match($event->update->callbackQuery->data);

                if ($action) {
                    $action = new $action();
                    $action->handle($event);
                    return response()
                        ->json([
                            'status' => true,
                            'error' => null
                        ]);;
                }
                echo 'No matched action';
                return $event->telegram->answerCallbackQuery([
                    'callback_query_id' => $event->update->callbackQuery->id,
                    'text' => 'Unfortunately, there is no matched action to respond to this callback',
                ]);
            });

            // hanndle all text messages
            $telegram->on('message.text', function (UpdateEvent $event) {
                $action = AbstractContext::match($event->update->message->text);

                if ($action) {
                    $action = new $action();
                    $action->handle($event);
                    return response()
                        ->json([
                            'status' => true,
                            'error' => null
                        ]);;
                }
                return response()
                    ->json([
                        'status' => true,
                        'error' => 'No matched action'
                    ]);
            });

            // handle command
            $telegram->commandsHandler(true);


            return response()
                ->json([
                    'status' => true,
                    'error' => null
                ]);
        } catch (\Throwable $exception) {
            return response()
                ->json([
                    'status' => false,
                    'error' => $exception->getMessage()
                ]);
        }
    }
}
