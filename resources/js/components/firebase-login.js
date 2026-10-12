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
                console.error('[firebase-login]', e);
                this.error = this.messageFor(e);
                this.loading = false;
            }
        },

        messageFor(e) {
            const code = e?.code ?? '';
            const hints = {
                'auth/popup-closed-by-user': '',
                'auth/cancelled-popup-request': '',
                'auth/popup-blocked': 'Popup diblokir browser. Izinkan popup lalu coba lagi.',
                'auth/unauthorized-domain': `Domain "${window.location.hostname}" belum didaftarkan di Firebase (Authentication → Settings → Authorized domains).`,
                'auth/operation-not-allowed': 'Provider Google belum diaktifkan di Firebase (Authentication → Sign-in method).',
                'auth/api-key-not-valid.-please-pass-a-valid-api-key.': 'FIREBASE_API_KEY tidak valid.',
                'auth/invalid-api-key': 'FIREBASE_API_KEY tidak valid.',
                'auth/network-request-failed': 'Koneksi ke Google gagal. Periksa internet Anda.',
            };

            if (code in hints) {
                return hints[code] ? `${hints[code]} (${code})` : '';
            }

            if (e?.response) {
                return `${e.response.data?.message ?? 'Server menolak login.'} (HTTP ${e.response.status})`;
            }

            return `Login Google gagal. Silakan coba lagi.${code ? ` (${code})` : e?.message ? ` (${e.message})` : ''}`;
        },
    };
}
