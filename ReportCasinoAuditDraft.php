<?php

namespace App\Models;

use App\Data\SlotData;
use App\Data\SlotBetDetailsData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReportCasino extends Model
{
    use HasFactory;

    protected $table = 'report_casino';

    public function getSlotBetsData(
        string $fromDate,
        string $toDate,
        int $pagesize,
        int $page,
        string|null $lastIdKey = null
    ): array {
        $lastId = null;
        $skip = ($page - 1) * $pagesize;

        // if ($page > 1) {
        //     $lastId = Cache::get($lastIdKey, null);
        // }

        $columns = [
            'rc.id AS betId',
            'rc.br_user_id AS playerId',
            DB::raw('CASE WHEN rc.bet_status = 0 THEN ABS(rc.bet_amount) ELSE 0 END AS totalBetAmount'),
            DB::raw('CASE WHEN rc.bet_status = 0 THEN 0 ELSE ABS(rc.bet_amount) END AS totalPayoutAmount'),
            'rc.settled_date_converted AS settledDate',
            DB::raw("CASE WHEN rc.bet_status = 0 THEN 'LOSE' WHEN rc.bet_status = 1 THEN 'WIN' ELSE 'CANCELLED' END AS betStatus"),
            'cg.game_name AS gameName',
            'cg.game_code AS gameId',
            'cg.category_name AS gameBrand',
        ];

        $slotsRawData = $this->select($columns)
            ->from('report_casino AS rc')
            ->join('casino_rooms AS cr', 'rc.casino_room', 'cr.casino_name')
            ->join('casino_games AS cg', function ($join) {
                $join->on('rc.game_id', '=', 'cg.game_code')
                    ->on('cr.id', 'cg.casino_room_id');
            })
            // ->when($lastId, function ($query) use ($lastId) {
            //     return $query->where('rc.id', '>', $lastId);
            // })
            ->when(is_null($lastId), function ($query) use ($skip) {
                return $query->skip($skip);
            })
            ->whereBetween('rc.settled_date_converted', [$fromDate, $toDate])
            ->whereIn('rc.bet_status', [0, 1, 2])
            ->whereIn('cg.game_type', ['slots', 'Fish Games'])
            ->orderBy('rc.id')
            ->take($pagesize)
            ->get()
            ->toArray();

        $count = 0;
        $data = [];
        foreach ($slotsRawData as $slot) {
            $data[] = new SlotData(
                $slot['betId'],
                $slot['playerId'],
                'ONLINE',
                'MAINOUTLET',
                $slot['totalBetAmount'],
                $slot['totalPayoutAmount'],
                $slot['settledDate'],
                $slot['settledDate'],
                '',
                $slot['betStatus'],
                new SlotBetDetailsData(
                    $slot['gameName'],
                    $slot['gameId'],
                    $slot['gameBrand'],
                    0,
                    '',
                    0,
                )
            );
            $count++;
            // $lastId = $slot['betId'];
        }

        // Cache::put($lastIdKey, $lastId);

        return [
            'data' => $data,
            'count' => $count,
        ];
    }

    public function getLiveBetData(string $fromDate,
        string $toDate,
        int $pagesize,
        int $page,
        string|null $lastIdKey = null
    ): array {
        $lastId = null;
        $skip = ($page - 1) * $pagesize;

        // if ($page > 1) {
        //     $lastId = Cache::get($lastIdKey, null);
        // }

        $columns = [
            'rc.id AS betId',
            'rc.br_user_id AS playerId',
            DB::raw('CASE WHEN rc.bet_status = 0 THEN ABS(rc.bet_amount) ELSE 0 END AS totalBetAmount'),
            DB::raw("CASE WHEN rc.bet_status = 0 THEN 0 ELSE ABS(rc.bet_amount) END AS totalPayoutAmount"),
            'rc.settled_date_converted AS settledDate',
            DB::raw("CASE WHEN rc.bet_status = 0 THEN 'LOSE' WHEN rc.bet_status = 1 THEN 'WIN' ELSE 'CANCELLED' END AS betStatus"),
            'cg.game_name AS gameName',
            'cg.game_code AS gameId',
            'cg.category_name AS gameBrand',
            'rc.bet_game_id AS liveRoundId'
        ];

        return $this->select($columns)
            ->from('report_casino AS rc')
            ->join('casino_rooms AS cr', 'rc.casino_room', 'cr.casino_name')
            ->join('casino_games AS cg', function ($join) {
                $join->on('rc.game_id', '=', 'cg.game_code')
                    ->on('cr.id', 'cg.casino_room_id');
            })
            // ->when($lastId, function ($query) use ($lastId) {
            //     return $query->where('rc.id', '>', $lastId);
            // })
            ->when(is_null($lastId), function ($query) use ($skip) {
                return $query->skip($skip);
            })
            ->whereBetween('rc.settled_date_converted', [$fromDate, $toDate])
            ->whereIn('rc.bet_status', [0, 1, 2])
            ->whereIn('cg.game_type', ['live'])
            ->orderBy('rc.id')
            ->take($pagesize)
            ->get()
            ->toArray();
    }
}
