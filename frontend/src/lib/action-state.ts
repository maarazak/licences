/** État renvoyé par les actions serveur aux formulaires. */
export type ActionState = {
  error?: string;
  success?: string;
  /** Étape suivante d'un formulaire en plusieurs temps (connexion). */
  step?: "code";
  email?: string;
};

export const INITIAL_STATE: ActionState = {};
