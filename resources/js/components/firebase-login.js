/**
 * Login Google lewat Firebase Authentication (popup), lalu tukar ID token
 * dengan sesi Laravel lewat POST /auth/firebase.
 */
export default function firebaseLogin(config) {
    return {
        loading: false,
        error: '',

        async signIn() {
            if (this.loading) return;
            this.loading = true;
            this.error = '';

            try {
                const [{ initializeApp, getApps }, { getAuth, GoogleAuthProvider, signInWithPopup, signOut }] = await Promise.all([
                    import('firebase/app'),
                    import('firebase/auth'),
                ]);

                const app = getApps()[0] ?? initializeApp(config.firebase);
                const auth = getAuth(app);
                const provider = new GoogleAuthProvider();
                provider.setCustomParameters({ prompt: 'select_account' });

                const result = await signInWithPopup(auth, provider);
                const idToken = await result.user.getIdToken();
                // Sesi dikelola Laravel; Firebase client tidak perlu menyimpan login.
                await signOut(auth);

                const response = await window.axios.post(config.endpoint, { id_token: idToken });
                window.location.assign(response.data.redirect);
            } catch (e) {
                const cancelled = ['auth/popup-closed-by-user', 'auth/cancelled-popup-request'].includes(e?.code);
                this.error = cancelled
                    ? ''
                    : e?.response?.data?.message
                        ?? (e?.code === 'auth/popup-blocked'
                            ? 'Popup diblokir browser. Izinkan popup lalu coba lagi.'
                            : 'Login Google gagal atau dibatalkan. Silakan coba lagi.');
                this.loading = false;
            }
        },
    };
}
