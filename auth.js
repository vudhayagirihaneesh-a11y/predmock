const UserAuth = {
    // Check if user is logged in
    isLoggedIn() {
        return !!localStorage.getItem('user_email') && !!localStorage.getItem('session_token');
    },
    
    // Get current user's email
    getCurrentUserEmail() {
        return localStorage.getItem('user_email');
    },
    
    // Get session token
    getSessionToken() {
        return localStorage.getItem('session_token');
    },

    // Get current user's full profile
    getCurrentUserProfile() {
        const profile = localStorage.getItem('user_profile');
        return profile ? JSON.parse(profile) : null;
    },
    
    // Get all user data stored
    getAllUserData() {
        return {
            email: localStorage.getItem('user_email'),
            id: localStorage.getItem('user_id'),
            verified: localStorage.getItem('user_verified'),
            created_at: localStorage.getItem('user_created_at'),
            login_time: localStorage.getItem('user_login_time'),
            session_token: localStorage.getItem('session_token'),
            login_timestamp: localStorage.getItem('login_timestamp'),
            profile: localStorage.getItem('user_profile') ? JSON.parse(localStorage.getItem('user_profile')) : null
        };
    },
    
    // Logout user
    logout() {
        const keys = [
            'user_email', 
            'user_id', 
            'user_verified', 
            'user_created_at', 
            'user_login_time', 
            'user_profile', 
            'session_token', 
            'login_timestamp',
            'pending_payment_order_id',
            'pending_payment_plan',
            'pending_payment_amount',
            'pending_payment_started_at',
            'met_all_tests_unlocked', 
            'vit_all_tests_unlocked', 
            'srm_all_tests_unlocked', 
            'bitsat_all_tests_unlocked',
            'jee_all_tests_unlocked',
            'amrita_all_tests_unlocked',
            'gitam_all_tests_unlocked'
        ];
        keys.forEach(k => localStorage.removeItem(k));
        window.location.href = 'login.html';
    },

    // Redirect to login if not authenticated
    requireAuth(redirectPage = window.location.pathname) {
        if (!this.isLoggedIn()) {
            const path = window.location.pathname.split('/').pop();
            window.location.href = `login.html?redirect=${encodeURIComponent(path)}`;
        }
    }
};
