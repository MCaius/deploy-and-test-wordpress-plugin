import { resetPluginState, runWpEval } from './helpers/wp-cli.mjs';

function ensureUser(login, email, password, role) {
    runWpEval(`
        $user = get_user_by( 'login', '${login}' );

        if ( ! $user ) {
            $user_id = wp_create_user( '${login}', '${password}', '${email}' );
            $user = get_user_by( 'id', $user_id );
        }

        wp_set_password( '${password}', $user->ID );
        $user->set_role( '${role}' );
    `);
}

export default async function globalSetup() {
    ensureUser('qa-editor', 'qa-editor@example.test', 'qa-editor-password', 'editor');
    ensureUser('qa-subscriber', 'qa-subscriber@example.test', 'qa-subscriber-password', 'subscriber');
    resetPluginState();
}
