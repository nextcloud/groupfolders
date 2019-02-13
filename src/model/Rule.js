
import PROPERTIES from 'Properties'

export default class Rule {

	constructor (props) {
		this.mappingType = props[PROPERTIES.PROPERTY_ACL_MAPPING_TYPE];
		this.mappingType = props[PROPERTIES.PROPERTY_ACL_MAPPING_ID];
		this.mask = props[PROPERTIES.PROPERTY_ACL_MASK];
		this.permissions = props[PROPERTIES.PROPERTY_ACL_PERMISSIONS];
	}


}
