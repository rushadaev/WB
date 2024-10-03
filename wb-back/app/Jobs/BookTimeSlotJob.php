<?php

namespace App\Jobs;

use App\Services\NodeApiService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use Illuminate\Queue\SerializesModels;
use App\Jobs\SendTelegramMessage;

class BookTimeSlotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cabinetId;
    protected $preorderId;
    protected $warehouseId;
    protected $deliveryDate;
    protected $monopalletCount;
    protected $chatId;
    protected $userId;
    protected $botToken;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($cabinetId, $preorderId, $warehouseId, $deliveryDate, $monopalletCount = null, $chatId, $userId, $botToken)
    {
        $this->cabinetId = $cabinetId;
        $this->preorderId = $preorderId;
        $this->warehouseId = $warehouseId;
        $this->deliveryDate = $deliveryDate;
        $this->monopalletCount = $monopalletCount;
        $this->chatId = $chatId;
        $this->userId = $userId;
        $this->botToken = $botToken;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(NodeApiService $nodeApiService)
    {
        try{
            
            $preorderId = htmlspecialchars($this->preorderId, ENT_QUOTES, 'UTF-8');
            $url = "https://seller.wildberries.ru/supplies-management/all-supplies/supply-detail/uploaded-goods?preorderId={$preorderId}&supplyId";

            $user = User::find($this->userId);
            if(!$user){
                Log::error("No user found with id ".$this->userId);
                return;
            }
            $autobookingsBalance = $user->autobookings;

            if($autobookingsBalance == 0){
                $message = "Мы не смогли забронировать слот, потому что у вас недостаточно автобронирований. \nПожалуйста, пополните баланс.\n\nСсылка на предзаказ тут: <a href=\"{$url}\">Предзаказ</a>";
                $this->notifyUserBalance($this->chatId, $message);
                return;
            }
            

            $nodeApiService->bookTimeSlot(
                $this->cabinetId,
                $this->preorderId,
                $this->warehouseId,
                $this->deliveryDate,
                $this->monopalletCount
            );

           
            if($user->autobookings > 0)
            {
                \Log::info("reducing autobookings");
                // Reducing autobookings
                $user->autobookings = $user->autobookings - 1;
                $user->save();
            }

            
            // Append the booking message with a clickable link
            $message = "Таймслот забронирован, ссылка на предзаказ тут: <a href=\"{$url}\">Предзаказ</a>";

            
            $this->notifyUser($this->chatId, $message);






        } catch (Exception $e) {
            Log::error("Failed to book time slot for Cabinet ID: {$this->cabinetId}, Preorder ID: {$this->preorderId}. Error: " . $e->getMessage());
        }
        
    }


    protected function notifyUser($chatId, $message)
    {
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '👌 Главное меню', 'callback_data' => 'mainmenu']]
        ]);

        SendTelegramMessage::dispatch($chatId, $message, 'HTML', $keyboard, $this->botToken);
    }

    protected function notifyUserBalance($chatId, $message)
    {
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '💎 Пополнить баланс', 'callback_data' => 'payments']],
            [['text' => '👌 Главное меню', 'callback_data' => 'mainmenu']]
        ]);

        SendTelegramMessage::dispatch($chatId, $message, 'HTML', $keyboard, $this->botToken);
    }
}
