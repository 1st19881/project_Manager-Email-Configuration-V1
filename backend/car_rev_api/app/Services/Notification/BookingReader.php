<?php

namespace App\Services\Notification;

use App\Models\TrcRequestRecord;

/**
 * Reads booking details straight from the legacy car booking tables, so the
 * booking system only ever has to send an event code and a document id.
 */
class BookingReader
{
    public function find(string $documentId): ?BookingSnapshot
    {
        $row = TrcRequestRecord::query()
            ->from('TRC_REQUEST_RECORD_NEW as b')
            ->leftJoin('INTRA.USERS as u', 'u.USERS_USERNAME', '=', 'b.USER_ID')
            ->where('b.DOC_ID', $documentId)
            ->selectRaw(implode(', ', [
                'b.DOC_ID as doc_id',
                'b.USER_ID as user_id',
                'b.PLANT as plant',
                'b.MGR_SELECT_ID as mgr_select_id',
                'b.USED_DATE as used_date',
                'b.USED_TIME as used_time',
                'b.BACK_DATE as back_date',
                'b.BACK_TIME as back_time',
                'b.REQUEST_GOTO as request_goto',
                'b.REQUEST_PROVINCE as request_province',
                'b.SUBJECT_ID as subject_id',
                'b.CAR_LICENSE as car_license',
                'b.TEL as tel',
                'u.USERS_FNAMETH as requester_fname',
                'u.USERS_LNAMETH as requester_lname',
                'u.USERS_DEPARTMENT as requester_department',
            ]))
            ->first();

        return $row === null ? null : BookingSnapshot::fromRow((object) $row->getAttributes());
    }
}
