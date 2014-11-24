<?php

class Crunchbutton_Call extends Cana_Table {
	public function __construct($id = null) {
		parent::__construct();
		$this
			->table('call')
			->idVar('id_call')
			->load($id);
	}
	
	public static function byTwilioId($id) {
		if (!$id) {
			return null;
		}
		return self::q('select * from `call` where twilio_id="'.c::db()->escape($id).'" limit 1')->get(0);
	}
	
	public static function logFromTwilio($data) {
		$call = self::byTwilioId($data['CallSid']);
		if (!$call->id_call) {
			$call = self::createFromTwilio($data);
		}
		return $call;
	}

	public static function createFromTwilio($data) {
		$call = new Call([
			'data' => json_encode($data),
			'date' => date('Y-m-d H:i:s'),
			'direction' => 'inbound',
			'twilio_id' => $data['CallSid'],
			'status' => $data['CallStatus'],
			'from' => self::cleanPhone($data['From']),
			'to' => self::cleanPhone($data['To']),
			'location_to' => $data['ToCity'].', '.$data['ToState'].' '.$data['ToZip'],
			'location_from' => $data['FromCity'].', '.$data['FromState'].' '.$data['FromZip'],
		]);
		$call->associateForeignKeys();
		$call->save();

		return $call;
	}
	
	public static function cleanPhone($num) {
		return str_replace('+1', '', $num);
	}
	
	public function associateForeignKeys() {
		if ($this->direction == 'outbound') {
			$this->id_admin_to = Admin::q('select * from admin where active=1 and phone="'.$this->to.'" limit 1')->get(0)->id_admin;
			$this->id_user_to = Admin::q('select * from user where active=1 and phone="'.$this->to.'" order by id_user desc limit 1')->get(0)->id_user;

		} elseif ($this->direction == 'inbound') {
			$this->id_admin_from = Admin::q('select * from admin where active=1 and phone="'.$this->from.'" limit 1')->get(0)->id_admin;
			$this->id_user_from = Admin::q('select * from user where active=1 and phone="'.$this->from.'" order by id_user desc limit 1')->get(0)->id_user;
			
			$this->id_support = Admin::q('
				select support.* from support
				left join support_message using(id_support)
				where
					support.phone="'.$this->from.'"
					and datediff(now(), support_message.date) < 1
				order by support.id_support desc
				limit 1
			')->get(0)->id_suport;
		}
	}
}