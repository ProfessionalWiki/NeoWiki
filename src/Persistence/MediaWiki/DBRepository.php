<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;
use RuntimeException;

class DBRepository {
	private LBFactory $lbFactory;
	private IDatabase $db;

	public function __construct( MediaWikiServices $services ) {
		$this->lbFactory = $services->getDBLoadBalancerFactory();
		$connection = $this->lbFactory->getMainLB()->getConnection( (int)DB_PRIMARY );

		if ( !$connection ) {
			throw new RuntimeException( 'No connection to the database' );
		}
		$this->db = $connection;
	}

	public function getDb(): IDatabase {
		return $this->db;
	}

	public function getLbFactory(): LBFactory {
		return $this->lbFactory;
	}
}
