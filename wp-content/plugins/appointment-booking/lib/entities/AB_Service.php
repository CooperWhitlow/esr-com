<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_Service
 */
class AB_Service extends AB_Entity {

    protected static $table_name = 'ab_service';

    protected static $schema = array(
        'id'          => array( 'format' => '%d' ),
        'title'       => array( 'format' => '%s' ),
        'duration'    => array( 'format' => '%d', 'default' => 900 ),
        'price'       => array( 'format' => '%.2f', 'default' => '0' ),
        'category_id' => array( 'format' => '%d' ),
        'color'       => array( 'format' => '%s' ),
        'capacity'    => array( 'format' => '%d', 'default' => '1' ),
        'position'    => array( 'format' => '%d', 'default' => 9999 ),
    );

    /**
     * @return string
     */
    public function getTitleWithDuration()
    {
        return sprintf( '%s (%s)', $this->getTitle(), self::durationToString( $this->get( 'duration' ) ) );
    }

    /**
     * Get title (if empty returns "Untitled").
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->get( 'title' ) != '' ? $this->get( 'title' ) : __( 'Untitled', 'ab' );
    }

    /**
     * Get category name.
     *
     * @return string
     */
    public function getCategoryName()
    {
        if ( $this->get( 'category_id' ) ) {
            $category = new AB_Category();
            $category->load( $this->get( 'category_id' ) );

            return $category->get( 'name' );
        }

        return __( 'Uncategorized', 'ab' );
    }

    /**
     * Convert number of seconds into string "[XX hr] XX min".
     *
     * @param int $duration
     * @return string
     */
    public static function durationToString( $duration )
    {
        $hours   = (int)( $duration / 3600 );
        $minutes = (int)( ( $duration % 3600 ) / 60 );
        $result  = '';
        if ( $hours > 0 ) {
          $result = sprintf( __( '%d h', 'ab' ), $hours );
          if ( $minutes > 0 ) {
            $result .= ' ';
          }
        }
        if ( $minutes > 0 ) {
          $result .= sprintf( __( '%d min', 'ab' ), $minutes );
        }

        return $result;
    }
}
