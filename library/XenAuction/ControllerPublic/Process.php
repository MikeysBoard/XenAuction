<?php

class XenAuction_ControllerPublic_Process extends XenForo_ControllerPublic_Abstract
{
	
	public function actionCreate()
	{
		return $this->responseView('XenAuction_ViewPublic_Auction_Create', 'auction_create', array());
	}
	
	public function actionAdd()
	{
		$visitor = XenForo_Visitor::getInstance();
		
		$input = $this->_input->filter(array(
			'title'        		=> XenForo_Input::STRING,
			'tags'         		=> XenForo_Input::STRING,
			'message_html' 		=> XenForo_Input::STRING,
			'expires'      		=> XenForo_Input::ARRAY_SIMPLE,
			'starting_bid' 		=> XenForo_Input::UINT,
			'buyout'       		=> XenForo_Input::UINT,
			'availability' 		=> XenForo_Input::UINT,
			'bid_enable'   		=> XenForo_Input::UINT,
			'buyout_enable'		=> XenForo_Input::UINT
		));
		
		$data = array(
			'user_id'			=> $visitor->user_id,
			'title'          	=> $input['title'],
			'message'        	=> $input['message_html'],
			'tags'           	=> $input['tags'],
			'min_bid'        	=> $input['bid_enable'] ? $input['starting_bid'] : NULL,
			'buy_now'        	=> $input['buyout_enable'] ? $input['buyout'] : NULL,
			'availability'   	=> $input['buyout_enable'] ? $input['availability'] : NULL,
			'expiration_date'	=> mktime(0,0,0, (int) $input['expires']['month'], (int) $input['expires']['day'], (int) $input['expires']['year'])
		);
		
		$dw = XenForo_DataWriter::create('XenAuction_DataWriter_Auction');
		$dw->bulkSet($data);
		
		$dw->save();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('auctions')
		);
	}
	
	public function actionExpire() 
	{
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);

		XenAuction_CronEntry_Auction::runExpireAuction($id);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('auctions')
		);
	}
	
	/**
	 * Enforce registered-users only for all actions in this controller
	 *
	 * @see library/XenForo/XenForo_Controller#_preDispatch($action)
	 */
	protected function _preDispatch($action)
	{
		if (
			! XenForo_Visitor::getInstance()->hasPermission('auctions', 'viewAuctions') OR
			! XenForo_Visitor::getInstance()->hasPermission('auctions', 'createAuctions')
		)
		{
			throw new XenForo_ControllerResponse_Exception($this->responseNoPermission());
		}
	}
	
}