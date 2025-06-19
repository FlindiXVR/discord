<?php

namespace IPS\discord;

use IPS\Db\Select;
use IPS\Member;

class DiscordMember extends Member
{
    protected static array $databaseIdFields = ['member_id', 'token_identifier'];

    protected static function constructLoadQuery(int|string $id, string $idField, mixed $extraWhereClause): Select
    {
        if ($handler = Discord::handler()) {
            $extraWhereClause = array_merge($extraWhereClause ?? [], ['token_login_method=?', $handler->id]);
        }

        $select = parent::constructLoadQuery($id, $idField, $extraWhereClause);

        return $select->join('core_login_links', 'core_login_links.token_member=core_members.member_id');
    }
}
