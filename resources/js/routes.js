/**
 * Centralized URL builders for every named backend route (see
 * routes/web.php). This project intentionally skips Ziggy (which would
 * normally provide a route() helper generated from the Laravel side) to
 * keep the dependency list small -- see the "Why no Ziggy" note in the
 * README. Keeping every path in this one file means a route only has
 * to be typed correctly once, instead of independently in every
 * component that links or posts to it.
 */
export const routes = {
    home: () => '/',

    login: () => '/login',
    logout: () => '/logout',
    register: () => '/register',
    forgotPassword: () => '/forgot-password',
    resetPassword: () => '/reset-password',
    checkResetCode: () => '/reset-password/check',

    profileEdit: () => '/profile',
    profileUpdate: () => '/profile',
    profilePassword: () => '/profile/password',

    searchSuggest: (q) => withQuery('/search/suggest', { q }),
    addressesIndex: () => '/account/addresses',
    addressStore: () => '/account/addresses',
    addressUpdate: (id) => `/account/addresses/${id}`,
    addressDefault: (id) => `/account/addresses/${id}/default`,
    addressDestroy: (id) => `/account/addresses/${id}`,
    questionStore: (slug) => `/products/${slug}/questions`,

    couponApply: () => '/coupon',
    couponRemove: () => '/coupon',
    reviewStore: (slug) => `/products/${slug}/reviews`,

    productsIndex: (params = {}) => withQuery('/products', params),
    productShow: (slug) => `/products/${slug}`,

    cartIndex: () => '/cart',
    cartStore: () => '/cart',
    cartUpdate: (cartItemId) => `/cart/${cartItemId}`,
    cartDestroy: (cartItemId) => `/cart/${cartItemId}`,
    cartBundle: () => '/cart/bundle',

    wishlistIndex: () => '/wishlist',
    wishlistStore: () => '/wishlist',
    wishlistDestroy: (productId) => `/wishlist/${productId}`,

    checkoutIndex: () => '/checkout',
    checkoutStore: () => '/checkout',


    ordersIndex: () => '/orders',
    trackOrder: (params = {}) => withQuery('/track-order', params),
    page: (slug) => `/${slug}`,
    orderShow: (orderId) => `/orders/${orderId}`,
    orderCancel: (orderId) => `/orders/${orderId}/cancel`,
    orderInvoice: (orderId) => `/orders/${orderId}/invoice`,
    orderSuccess: (orderId) => `/orders/${orderId}/success`,

    admin: () => '/admin',
};

function withQuery(base, params) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, value);
        }
    });

    const queryString = query.toString();

    return queryString ? `${base}?${queryString}` : base;
}
