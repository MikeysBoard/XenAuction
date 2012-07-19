<?php

class XenAuction_ControllerPublic_Process extends XenForo_ControllerPublic_Abstract
{
	
	public function actionCreate()
	{
		$tagModel = XenForo_Model::create('XenAuction_Model_Tag');
		return $this->responseView('XenAuction_ViewPublic_Auction_Create', 'auction_create', array(
			'allTags'	=> $tagModel->getTags()
		));
	}
	
	public function actionAdd()
	{
		$visitor = XenForo_Visitor::getInstance();
		
		$upload  = XenForo_Upload::getUploadedFile('image');
		
		if ($upload)
		{
			$imagePath = XenAuction_DataWriter_Helper_Auction::saveImage($upload);
		}
		else
		{
			$imagePath = NULL;
		}
		
		$input = $this->_input->filter(array(
			'title'        		=> XenForo_Input::STRING,
			'tags'         		=> XenForo_Input::ARRAY_SIMPLE,
			'message_html' 		=> XenForo_Input::STRING,
			'expires'      		=> XenForo_Input::UINT,
			'batch'      		=> XenForo_Input::UINT,
			'starting_bid' 		=> XenForo_Input::UINT,
			'buyout'       		=> XenForo_Input::UINT,
			'availability' 		=> XenForo_Input::UINT,
			'bid_enable'   		=> XenForo_Input::UINT,
			'buyout_enable'		=> XenForo_Input::UINT
		));
		
		$batch = is_numeric($input['batch']) ? $input['batch'] : 1;
		if ($batch < 1)
		{
			$batch = 1;
		}
		
		$data = array(
			'user_id'			=> $visitor->user_id,
			'title'          	=> $input['title'],
			'image'				=> $imagePath,
			'message'        	=> $input['message_html'],
			'min_bid'        	=> $input['bid_enable'] ? $input['starting_bid'] : NULL,
			'buy_now'        	=> $input['buyout_enable'] ? $input['buyout'] : NULL,
			'availability'   	=> $input['buyout_enable'] ? $input['availability'] : NULL,
			'expiration_date'	=> time() + ((int) $input['expires'] * 86400)
		);
		
		$tags 		= array_unique(array_filter($input['tags']));
		$tagModel 	= XenForo_Model::create('XenAuction_Model_Tag');
		
		for ($c=0;$c<$batch; $c++)
		{
			$dw = XenForo_DataWriter::create('XenAuction_DataWriter_Auction');
			$dw->bulkSet($data);
			$dw->save();
			
			$auction = $dw->getMergedData();
			
			$tagModel->addTagToAuction($tags, $auction['auction_id']);
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('auctions')
		);
	}
	
	public function actionArchive()
	{
		$id 		= $this->_input->filterSingle('id', XenForo_Input::UINT);
		$visitor 	= XenForo_Visitor::getInstance();
		
		$auctionModel	= XenForo_Model::create('XenAuction_Model_Auction');
		$auction     	= $auctionModel->getAuctionById($id);
		
		if ($visitor->user_id != $auction['user_id'])
		{
			return $this->responseNoPermission();
		}
		
		$dw = XenForo_DataWriter::create('XenAuction_DataWriter_Auction');
		$dw->setExistingData($auction);
		$dw->set('archived', 1);
		
		$dw->preSave();

		if ($dwErrors = $dw->getErrors())
		{
			return $this->responseError($dwErrors);
		}
		
		$dw->save();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('auction-history')
		);
	}
	
	public function actionComplete()
	{
		$visitor 	= XenForo_Visitor::getInstance();
		if ( ! isset($visitor->customFields['auctionEnableConfirm']) OR $visitor->customFields['auctionEnableConfirm'][1] != 1)
		{
			return $this->actionMarkComplete();
		}
		
		$id 			= $this->_input->filterSingle('id', XenForo_Input::UINT);
		
		$fetchOptions	= array('join'	 => array(XenAuction_Model_Auction::FETCH_USER, XenAuction_Model_Auction::FETCH_BID));
		$fetchConditions= array('bid_id' => $id);
		
		$auctionModel	= XenForo_Model::create('XenAuction_Model_Auction');
		$auction     	= $auctionModel->getAuctions($fetchConditions, $fetchOptions);
		
		if ( ! $auction)
		{
			return $this->responseError(new XenForo_Phrase('sale_not_found'), 404);
		}
		
		$auction = current($auction);
		
		$message = isset($visitor->customFields['auctionConfirmMessage']) ? $visitor->customFields['auctionConfirmMessage'] : '';
		if ( ! empty($message))
		{
			$auction['link'] 	= XenForo_Link::buildPublicLink('auctions/details', '', array('id' => $auction['auction_id']));
			$message 			= preg_replace('|\{([a-z]*?)\}|e', '"".$auction["$1"].""', $message);
		}
		
		return $this->responseView('XenForo_ViewPublic_Base', 'auction_complete', array(
			'auction'	=> $auction,
			'message'	=> $message
		));	
	}
	
	public function actionMarkComplete()
	{
		$visitor 		= XenForo_Visitor::getInstance();
		$id 			= $this->_input->filterSingle('id', XenForo_Input::UINT);
		$message 		= $this->_input->filterSingle('message', XenForo_Input::STRING);
		
		$fetchOptions	= array('join'	 => array(XenAuction_Model_Auction::FETCH_BID));
		$fetchConditions= array('bid_id' => $id);
		
		$auctionModel	= XenForo_Model::create('XenAuction_Model_Auction');
		$auction     	= $auctionModel->getAuctions($fetchConditions, $fetchOptions);
		
		if ( ! $auction)
		{
			return $this->responseError(new XenForo_Phrase('sale_not_found'), 404);
		}
		
		$auction = current($auction);
		
		if ($visitor->user_id != $auction['user_id'])
		{
			return $this->responseNoPermission();
		}
		
		$dw = XenForo_DataWriter::create('XenAuction_DataWriter_Bid');
		$dw->setExistingData($auction);
		$dw->set('completed', 1);
		
		$dw->preSave();

		if ($dwErrors = $dw->getErrors())
		{
			return $this->responseError($dwErrors);
		}
		
		$dw->save();
		
		if ( ! empty($message))
		{
			$title = new XenForo_Phrase('sale_x_completed', $auction);
			XenAuction_Helper_Notification::sendNotification($auction['bid_user_id'], $title, $message, $visitor->toArray());
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('auction-history')
		);
	}
	
	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('creating_auctions');
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