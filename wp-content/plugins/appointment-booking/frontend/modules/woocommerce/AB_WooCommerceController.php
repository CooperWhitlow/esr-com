<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class AB_WooCommerceController
 */
class AB_WooCommerceController extends AB_Controller
{
    private $product_id = 0;

    protected function getPermissions()
    {
        return array(
            '_this' => 'anonymous',
        );
    }

    public function __construct()
    {
        $this->product_id = get_option( 'ab_woocommerce_product', 0 );

        add_action( 'woocommerce_get_item_data', array( $this, 'getItemData' ), 10, 2 );
        add_action( 'woocommerce_payment_complete', array( $this, 'paymentComplete' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'paymentComplete' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'paymentComplete' ) );
        add_action( 'woocommerce_add_order_item_meta', array( $this, 'addOrderItemMeta' ), 10, 3 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'beforeCalculateTotals' ) );
        add_filter( 'woocommerce_quantity_input_args', array( $this, 'quantityArgs' ), 10, 2 );

        add_action( 'woocommerce_after_order_itemmeta', array( $this, 'afterOrderItemMeta' ) );

        parent::__construct();
    }

    /**
     * Do bookings after checkout.
     *
     * @param $order_id
     */
    public function paymentComplete( $order_id )
    {
        $order = new WC_Order( $order_id );
        foreach ( $order->get_items() as $item_id => $order_item ) {
            $data = wc_get_order_item_meta( $item_id, 'bookly' );
            if ( $data && ! isset ( $data['processed'] ) ) {
                $book = new AB_UserBookingData( null );
                $book->setData( $data );
                $book->save();
                // Mark item as processed.
                $data['processed'] = true;
                wc_add_order_item_meta( $item_id, 'bookly', $data );
            }
        }
    }

    /**
     * Change attr for WC quantity input
     */
    function quantityArgs( $args, $product )
    {
        if ( $product->id == $this->product_id ) {
            $args['max_value'] = $args['input_value'];
            $args['min_value'] = $args['input_value'];
        }

        return $args;
    }

    /**
     * Change item price in cart.
     *
     * @param $cart_object
     */
    public function beforeCalculateTotals( $cart_object )
    {
        foreach ( $cart_object->cart_contents as $key => $value ) {
            if ( isset ( $value['bookly'] ) ) {
                $userData = new AB_UserBookingData( null );
                $userData->setData( $value['bookly'] );
                $value['data']->price = $userData->getFinalServicePrice();
            }
        }
    }

    public function addOrderItemMeta( $item_id, $values, $cart_item_key )
    {
        if ( isset ( $values['bookly'] ) ) {
            wc_add_order_item_meta( $item_id, 'bookly', $values['bookly'] );
        }
    }

    /*
     * Get item data for cart.
     */
    function getItemData( $other_data, $cart_item )
    {
        if ( isset ( $cart_item['bookly'] ) ) {
            $info_name  = get_option( 'ab_woocommerce_cart_info_name' );
            $info_value = get_option( 'ab_woocommerce_cart_info_value' );

            $staff = new AB_Staff();
            $staff->load( $cart_item['bookly']['staff_ids'][0] );

            $service = new AB_Service();
            $service->load( $cart_item['bookly']['service_id'] );

            $info_value = strtr( $info_value, array(
                '[[APPOINTMENT_TIME]]' => AB_DateTimeUtils::formatTime( $cart_item['bookly']['appointment_datetime'] ),
                '[[APPOINTMENT_DATE]]' => AB_DateTimeUtils::formatDate( $cart_item['bookly']['appointment_datetime'] ),
                '[[CATEGORY_NAME]]'    => $service->getCategoryName(),
                '[[SERVICE_NAME]]'     => $service->getTitle(),
                '[[SERVICE_PRICE]]'    => $service->get( 'price' ),
                '[[STAFF_NAME]]'       => $staff->get( 'full_name' ),
            ) );

            $other_data[] = array( 'name' => $info_name, 'value' => $info_value );
        }

        return $other_data;
    }

    /**
     * Print appointment details inside order items in the backend.
     *
     * @param $item_id
     */
    public function afterOrderItemMeta( $item_id )
    {
        $data = wc_get_order_item_meta( $item_id, 'bookly' );
        if ( $data ) {
            $other_data = $this->getItemData( array(), array( 'bookly' => $data ) );
            echo $other_data[0]['name'] . '<br/>' . nl2br( $other_data[0]['value'] );
        }
    }

    /**
     * Add product to cart
     *
     * @return string JSON
     */
    public function executeAddToWoocommerceCart()
    {
        $form_id = $this->getParameter( 'form_id' );

        $response = null;

        if ( $form_id ) {
            $userData = new AB_UserBookingData( $form_id );
            $userData->load();
            if ( $userData->getFinalServicePrice() > 0 ) {
                WC()->cart->add_to_cart( $this->product_id, $userData->get( 'number_of_persons' ), '', array(), array( 'bookly' => $userData->getData() ) );
                $response = array(
                    'status' => 'success',
                );
            }
        }

        // Output JSON response.
        if ( $response === null ) {
            $response = array( 'status' => 'no-data' );
        }
        header( 'Content-Type: application/json' );
        echo json_encode( $response );

        exit ( 0 );
    }

    /**
     * Override parent method to add 'wp_ajax_ab_' prefix
     * so current 'execute*' methods look nicer.
     */
    protected function registerWpActions( $prefix = '' ) {
        parent::registerWpActions( 'wp_ajax_ab_' );
        parent::registerWpActions( 'wp_ajax_nopriv_ab_' );
    }
}