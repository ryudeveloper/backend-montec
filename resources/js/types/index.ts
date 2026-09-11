/** Espelha o que HandleInertiaRequests compartilha. Manter os dois em sintonia. */
export interface SharedUser {
  readonly name: string;
  readonly email: string;
  readonly roleLabels: string;
}

/**
 * Props compartilhadas.
 *
 * `auth` é declarada opcional de propósito: a tela de erro pode ser renderizada
 * por um caminho que não passa pelo middleware do Inertia, e o tipo precisa
 * obrigar quem consome a tratar essa ausência em vez de estourar em runtime.
 */
export interface SharedProps {
  /*
   * Índice exigido pelo PageProps do Inertia. Declarado explicitamente para não
   * cair num `any` implícito — CLAUDE.md proíbe, e aqui `unknown` é honesto: o
   * Inertia pode acrescentar props que este tipo não enumera.
   */
  readonly [key: string]: unknown;
  readonly auth?: {
    readonly user: SharedUser | null;
    /**
     * Dica de interface, nunca autorização. Quem decide é o EnsureRole no
     * servidor — guarda no cliente é cosmético, porque o cliente é do usuário.
     */
    readonly can: {
      readonly viewResumes: boolean;
      readonly viewReports: boolean;
      readonly viewAudit: boolean;
    };
  };
  readonly flash: { readonly success: string | null };
  readonly errors?: Record<string, string>;
}

/**
 * Metadados de paginação em forma explícita.
 *
 * Não é o array `links` do Laravel de propósito: os rótulos dele trazem
 * entidades HTML e exigiriam injeção de HTML no cliente.
 */
export interface PageMeta {
  readonly currentPage: number;
  readonly lastPage: number;
  readonly total: number;
  readonly prevUrl: string | null;
  readonly nextUrl: string | null;
}

export interface Paginated<T> {
  readonly data: readonly T[];
  readonly meta: PageMeta;
}

export interface JobApplicationSummary {
  readonly id: string;
  readonly name: string;
  readonly email: string;
  readonly jobOpening: string;
  readonly receivedAt: string;
  /** Já formatado no servidor — "412 kB", "2,4 MB", "90 B". */
  readonly resumeSize: string;
  readonly resumeUrl: string;
  readonly detailUrl: string;
}

export interface JobApplicationDetail extends JobApplicationSummary {
  readonly phone: string;
  readonly message: string | null;
  readonly resumeOriginalName: string;
  readonly consentAcceptedAt: string | null;
}

/**
 * Resumo de denúncia na listagem.
 *
 * Sem o texto do relato de propósito: uma prévia furava a auditoria, porque numa
 * denúncia curta ela era o relato inteiro — e aí dava para ler tudo sem deixar
 * registro. Todo acesso ao conteúdo passa pela tela de detalhe, que é auditada.
 */
export interface ReportSummary {
  readonly id: string;
  readonly subject: string;
  readonly isAnonymous: boolean;
  readonly receivedAt: string;
  readonly detailUrl: string;
}

export interface ReportDetail {
  readonly id: string;
  readonly subject: string;
  readonly description: string;
  readonly isAnonymous: boolean;
  readonly receivedAt: string;
  /** Só presentes quando o denunciante escolheu se identificar. */
  readonly name: string | null;
  readonly email: string | null;
}

export interface AuditEntry {
  readonly id: number;
  readonly operator: string;
  readonly operatorEmail: string | null;
  readonly action: string;
  readonly resourceType: string;
  /** Rótulo do recurso, ou "registro expurgado" quando a retenção já o apagou. */
  readonly subject: string;
  readonly ip: string | null;
  readonly at: string | null;
}

/**
 * Parecer de aderência gerado por IA.
 *
 * Sem campo de aprovação nem de status: o sistema não decide. Art. 20 da LGPD —
 * decisão unicamente automatizada dá ao candidato direito de revisão.
 */
export interface Assessment {
  readonly score: number;
  readonly strengths: readonly string[];
  readonly gaps: readonly string[];
  readonly skills: readonly string[];
  readonly model: string;
  readonly generatedAt: string;
}
