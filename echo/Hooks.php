<?php

namespace FlowThread;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class EchoHooks {

	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories, &$icons ) {
		$icons += [
			'flowthread-delete' => [
				'path' => 'FlowThread/assets/delete.svg'
			],
		];

		$notificationCategories['flowthread'] = [
			'priority' => 4,
			'tooltip' => 'echo-pref-tooltip-flowthread',
		];
		$notifications['flowthread_reply'] = [
			'category' => 'flowthread',
			'group' => 'interactive',
			'section' => 'message',
			'presentation-model' => 'FlowThread\\EchoPresentationModel',
		];
		$notifications['flowthread_userpage'] = [
			'category' => 'flowthread',
			'group' => 'interactive',
			'section' => 'message',
			'presentation-model' => 'FlowThread\\EchoPresentationModel',
		];
		$notifications['flowthread_mention'] = [
			'category' => 'flowthread',
			'group' => 'interactive',
			'section' => 'message',
			'presentation-model' => 'FlowThread\\EchoPresentationModel',
		];
		$notifications['flowthread_delete'] = [
			'user-locators' => [
				'EchoUserLocator::locateEventAgent',
			],
			'category' => 'flowthread',
			'group' => 'negative',
			'section' => 'alert',
			'presentation-model' => 'FlowThread\\EchoAlertPresentationModel',
		];
		$notifications['flowthread_recover'] = [
			'user-locators' => [
				'EchoUserLocator::locateEventAgent',
			],
			'category' => 'flowthread',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => 'FlowThread\\EchoAlertPresentationModel',
		];
		$notifications['flowthread_spam'] = [
			'user-locators' => [
				'EchoUserLocator::locateEventAgent',
			],
			'category' => 'flowthread',
			'group' => 'negative',
			'section' => 'alert',
			'presentation-model' => 'FlowThread\\EchoAlertPresentationModel',
		];

		return true;
	}

	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			case 'flowthread_reply':
			case 'flowthread_mention':
			case 'flowthread_userpage':
				$extra = $event->getExtra();
				if ( !$extra || !isset( $extra['target-user-id'] ) ) {
					break;
				}
				$recipientId = $extra['target-user-id'];
				foreach ( $recipientId as $id ) {
					$recipient = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $id );
					$users[$id] = $recipient;
				}
				break;
		}

		return true;
	}

	public static function onFlowThreadPosted( $post ) {
		$poster = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $post->userid );
		$title = Title::newFromId( $post->pageid );

		$targets = [];
		$parent = $post->getParent();
		for ( ; $parent; $parent = $parent->getParent() ) {
			// If the parent post is anonymous, we generate no message
			if ( $parent->userid === 0 ) {
				continue;
			}
			// If the parent is the user himself, we generate no message
			if ( $parent->userid === $post->userid ) {
				continue;
			}
			$targets[] = $parent->userid;
		}
		Event::create( [
			'type' => 'flowthread_reply',
			'title' => $title,
			'extra' => [
				'target-user-id' => $targets,
				'postid' => $post->id->getBin(),
			],
			'agent' => $poster,
		] );

		// Check if posted on a user page
		if ( $title->getNamespace() === NS_USER && !$title->isSubpage() ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $title->getText() );
			// If user exists and is not the poster
			if ( $user && $user->getId() !== 0 && !$user->equals( $poster ) && !in_array( $user->getId(), $targets ) ) {
				Event::create( [
					'type' => 'flowthread_userpage',
					'title' => $title,
					'extra' => [
						'target-user-id' => [ $user->getId() ],
						'postid' => $post->id->getBin(),
					],
					'agent' => $poster,
				] );
			}
		}

		return true;
	}

	public static function onFlowThreadDeleted( $post, User $initiator ) {
		if ( $post->userid === 0 || $post->userid === $initiator->getId() ) {
			return true;
		}

		$poster = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $post->userid );
		$title = Title::newFromId( $post->pageid );

		Event::create( [
			'type' => 'flowthread_delete',
			'title' => $title,
			'extra' => [
				'notifyAgent' => true,
				'postid' => $post->id->getBin(),
			],
			'agent' => $poster,
		] );

		return true;
	}

	public static function onFlowThreadRecovered( $post, User $initiator ) {
		if ( $post->userid === 0 || $post->userid === $initiator->getId() ) {
			return true;
		}

		$poster = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $post->userid );
		$title = Title::newFromId( $post->pageid );

		Event::create( [
			'type' => 'flowthread_recover',
			'title' => $title,
			'extra' => [
				'notifyAgent' => true,
				'postid' => $post->id->getBin(),
			],
			'agent' => $poster,
		] );

		return true;
	}

	public static function onFlowThreadSpammed( $post ) {
		if ( $post->userid === 0 ) {
			return true;
		}

		$poster = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $post->userid );
		$title = Title::newFromId( $post->pageid );

		Event::create( [
			'type' => 'flowthread_spam',
			'title' => $title,
			'extra' => [
				'notifyAgent' => true,
				'postid' => $post->id->getBin(),
			],
			'agent' => $poster,
		] );

		return true;
	}

	public static function onFlowThreadMention( $post, $mentioned ) {
		$targets = [];
		foreach ( $mentioned as $id => $id2 ) {
			$targets[] = $id;
		}

		$poster = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $post->userid );
		$title = Title::newFromId( $post->pageid );

		Event::create( [
			'type' => 'flowthread_mention',
			'title' => $title,
			'extra' => [
				'target-user-id' => $targets,
				'postid' => $post->id->getBin(),
			],
			'agent' => $poster,
		] );

		return true;
	}

}
