import {
  ISlideOptionsContext,
  SlideOptionsModule, VueInstance
} from "@comeen/comeen-play-sdk-js";

export default class GoogleAccountOptionsModule extends SlideOptionsModule {
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
        )
      ]
    )
  }
}
