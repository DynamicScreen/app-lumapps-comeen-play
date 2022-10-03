import {
  ISlideOptionsContext,
  SlideOptionsModule, VueInstance
} from "@comeen/comeen-play-sdk-js";

export default class OAuthAccountOptionsModule extends SlideOptionsModule {
  constructor(context: ISlideOptionsContext) {
    super(context);
  }

  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideOptionsContext) {
    const { h } = vue;

    const update = context.update;

    const { Field, TextInput, Toggle } = this.context.components

    return () =>
      h("div", {}, [
        h(Field, { label: 'LumApps API endpoint' },
          h(TextInput, { ...update.option('endpoint_uri'), placeholder: 'https://' })
        ),
        h(Field, { label: 'Host' },
          h(TextInput, { ...update.option('host'), placeholder: '' })
        ),
        h(Field, { label: 'Organization ID' },
          h(TextInput, { ...update.option('organization_id'), placeholder: '' })
        ),
        h(Field, { label: 'Client ID' },
          h(TextInput, { ...update.option('client_id'), placeholder: '' })
        ),
        h(Field, { label: 'Client Secret' },
          h(TextInput, { ...update.option('client_secret'), placeholder: '' })
        )
      ]
    )
  }
}
