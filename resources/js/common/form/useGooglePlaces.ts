import { importLibrary, setOptions } from '@googlemaps/js-api-loader';
import { usePage } from '@inertiajs/vue3';

/**
 * The structured address the field hands back. Deliberately flat and
 * snake_case so it drops straight into a Spatie Data object server-side.
 */
export type ResolvedAddress = {
    formatted_address: string;
    place_id: string;
    street_number: string | null;
    route: string | null;
    locality: string | null;
    administrative_area: string | null;
    postal_code: string | null;
    country: string | null;
    country_code: string | null;
    latitude: number | null;
    longitude: number | null;
};

export type PlaceSuggestion = {
    placeId: string;
    /** The bold "main" line — usually the street address or business name. */
    primaryText: string;
    /** The greyed "secondary" line — city, region, country. */
    secondaryText: string;
    prediction: google.maps.places.PlacePrediction;
};

let placesLibrary: google.maps.PlacesLibrary | null = null;
let loadPromise: Promise<google.maps.PlacesLibrary> | null = null;

/**
 * Loads the Places library exactly once per page, no matter how many address
 * fields are mounted. `setOptions` must run before the first `importLibrary`.
 */
function loadPlaces(apiKey: string): Promise<google.maps.PlacesLibrary> {
    if (placesLibrary) {
        return Promise.resolve(placesLibrary);
    }

    if (!loadPromise) {
        setOptions({ key: apiKey, v: 'weekly' });

        loadPromise = importLibrary('places').then((library) => {
            placesLibrary = library;

            return library;
        });
    }

    return loadPromise;
}

const ADDRESS_COMPONENT_MAP: Record<string, keyof ResolvedAddress> = {
    street_number: 'street_number',
    route: 'route',
    locality: 'locality',
    postal_town: 'locality',
    administrative_area_level_1: 'administrative_area',
    postal_code: 'postal_code',
    country: 'country',
};

export function useGooglePlaces() {
    const page = usePage<{ googleMapsKey: string | null }>();

    const apiKey = () => page.props.googleMapsKey ?? '';

    /**
     * One token spans a whole "type → pick" interaction. Google bills the
     * autocomplete keystrokes as a single session only when the same token is
     * reused and then handed to the follow-up details request — without it
     * every keystroke is billed separately.
     */
    let sessionToken: google.maps.places.AutocompleteSessionToken | null = null;

    async function ensureSession(): Promise<google.maps.places.AutocompleteSessionToken> {
        const { AutocompleteSessionToken } = await loadPlaces(apiKey());

        if (!sessionToken) {
            sessionToken = new AutocompleteSessionToken();
        }

        return sessionToken;
    }

    async function search(
        input: string,
        options: {
            /** ISO 3166-1 alpha-2 codes, max 5, e.g. `['us', 'ca']`. */
            countries?: string[];
            /** e.g. `['address']` for street addresses only. */
            includedPrimaryTypes?: string[];
        } = {},
    ): Promise<PlaceSuggestion[]> {
        if (!apiKey()) {
            throw new Error(
                'GOOGLE_MAPS_API_KEY is not configured — set it in .env and expose it via config/services.php.',
            );
        }

        if (!input.trim()) {
            return [];
        }

        const { AutocompleteSuggestion } = await loadPlaces(apiKey());
        const token = await ensureSession();

        const { suggestions } =
            await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                input,
                sessionToken: token,
                ...(options.countries?.length
                    ? { includedRegionCodes: options.countries }
                    : {}),
                ...(options.includedPrimaryTypes?.length
                    ? { includedPrimaryTypes: options.includedPrimaryTypes }
                    : {}),
            });

        return suggestions
            .map((suggestion) => suggestion.placePrediction)
            .filter(
                (
                    prediction,
                ): prediction is google.maps.places.PlacePrediction =>
                    Boolean(prediction),
            )
            .map((prediction) => ({
                placeId: prediction.placeId,
                primaryText: prediction.mainText?.text ?? prediction.text.text,
                secondaryText: prediction.secondaryText?.text ?? '',
                prediction,
            }));
    }

    /**
     * Resolves a picked suggestion into a full address. This closes the billing
     * session, so the next keystroke starts a fresh one.
     */
    async function resolve(
        suggestion: PlaceSuggestion,
    ): Promise<ResolvedAddress> {
        const place = suggestion.prediction.toPlace();

        await place.fetchFields({
            fields: ['formattedAddress', 'addressComponents', 'location', 'id'],
        });

        sessionToken = null;

        const resolved: ResolvedAddress = {
            formatted_address: place.formattedAddress ?? suggestion.primaryText,
            place_id: place.id,
            street_number: null,
            route: null,
            locality: null,
            administrative_area: null,
            postal_code: null,
            country: null,
            country_code: null,
            latitude: place.location?.lat() ?? null,
            longitude: place.location?.lng() ?? null,
        };

        for (const component of place.addressComponents ?? []) {
            for (const type of component.types) {
                const key = ADDRESS_COMPONENT_MAP[type];

                if (!key) {
                    continue;
                }

                if (key === 'country') {
                    resolved.country = component.longText;
                    resolved.country_code = component.shortText;

                    continue;
                }

                if (resolved[key] === null) {
                    (resolved[key] as string | null) = component.longText;
                }
            }
        }

        return resolved;
    }

    return { search, resolve, isConfigured: () => Boolean(apiKey()) };
}
